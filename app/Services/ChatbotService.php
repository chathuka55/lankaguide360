<?php

namespace App\Services;

use App\Models\ChatLog;
use App\Models\District;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Place;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * LankaGuide360 travel assistant (SRS 6.5, FR-24).
 *
 * 1. retrieve matching published places, districts, packages and FAQs from MySQL
 * 2. ask a small instruct model (Hugging Face Inference Providers, OpenAI-compatible API)
 *    to answer briefly using only that context and site links
 * 3. sanitise the reply, keep only internal links (shown as buttons)
 * 4. if the API is unavailable, answer from the best-matching FAQ
 * Every exchange is stored in chat_logs.
 */
class ChatbotService
{
    private const MAX_HISTORY = 6;

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, links: array<int, array{label: string, url: string}>, source: string}
     */
    public function reply(string $message, array $history, string $sessionId, ?int $userId = null): array
    {
        $message = trim(strip_tags($message));
        $context = $this->retrieve($message);

        $answer = $this->askModel($message, $history, $context) ?? $this->faqAnswer($message, $context);
        $result = $this->sanitise($answer['text']) + ['source' => $answer['source']];

        // Links from the context are offered as buttons even if the model didn't write them.
        $result['links'] = collect([...$result['links'], ...($answer['links'] ?? [])])->unique('url')->take(4)->values()->all();

        ChatLog::create(['session_id' => $sessionId, 'user_id' => $userId, 'role' => 'user', 'message' => Str::limit($message, 2000)]);
        ChatLog::create(['session_id' => $sessionId, 'user_id' => $userId, 'role' => 'assistant', 'message' => Str::limit($result['reply'], 4000)]);

        return $result;
    }

    /**
     * Lightweight retrieval: FULLTEXT on places and FAQs, name matching for districts and packages.
     *
     * @return array{places: Collection, districts: Collection, packages: Collection, faqs: Collection}
     */
    public function retrieve(string $message): array
    {
        $terms = $this->searchTerms($message);
        $booleanQuery = collect($terms)->map(fn ($t) => $t.'*')->implode(' ');

        $places = $terms === [] ? collect() : Place::published()
            ->with('district')
            ->where(function ($q) use ($booleanQuery, $terms) {
                $q->whereFullText(['name', 'short_description'], $booleanQuery, ['mode' => 'boolean']);
                foreach ($terms as $term) {
                    $q->orWhere('places.name', 'like', "%{$term}%")
                        ->orWhere('places.short_description', 'like', "%{$term}%")
                        ->orWhereHas('categories', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('district', fn ($d) => $d->where('name', 'like', "%{$term}%"));
                }
            })
            ->orderByRaw("FIELD(crowd_level, 'high', 'medium', 'low')")
            ->limit(6)
            ->get();

        $districts = District::query()
            ->where(fn ($q) => collect($terms)->each(fn ($t) => $q->orWhere('name', 'like', "%{$t}%")))
            ->when($terms === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->limit(3)->get();

        $packages = Package::query()
            ->where(fn ($q) => collect($terms)->each(fn ($t) => $q->orWhere('name', 'like', "%{$t}%")->orWhere('summary', 'like', "%{$t}%")))
            ->when($terms === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->limit(3)->get();

        $faqs = $terms === [] ? collect() : Faq::active()
            ->whereFullText(['question', 'answer', 'keywords'], $booleanQuery, ['mode' => 'boolean'])
            ->limit(3)->get();

        return compact('places', 'districts', 'packages', 'faqs');
    }

    /**
     * @return array{text: string, links: array, source: string}|null
     */
    private function askModel(string $message, array $history, array $context): ?array
    {
        $token = config('services.huggingface.token');

        if (blank($token)) {
            return null;
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($context)]];
        foreach (array_slice($history, -self::MAX_HISTORY) as $turn) {
            if (in_array($turn['role'] ?? '', ['user', 'assistant'], true) && filled($turn['content'] ?? null)) {
                $messages[] = ['role' => $turn['role'], 'content' => Str::limit(strip_tags($turn['content']), 1000)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withToken($token)
                ->withUserAgent(config('lankaguide.user_agent'))
                ->acceptJson()
                ->timeout((int) config('services.huggingface.timeout'))
                ->post(config('services.huggingface.url'), [
                    'model' => config('services.huggingface.model'),
                    'messages' => $messages,
                    'max_tokens' => 400,
                    'temperature' => 0.3,
                ]);

            $text = $response->json('choices.0.message.content');

            if (! $response->successful() || ! is_string($text) || trim($text) === '') {
                Log::warning('Chatbot API failed, using FAQ fallback', ['status' => $response->status()]);

                return null;
            }

            // Some models include their reasoning in <think> tags: never show it.
            $text = trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));

            return ['text' => $text, 'links' => $this->contextLinks($context), 'source' => 'ai'];
        } catch (Throwable $e) {
            Log::warning('Chatbot API error, using FAQ fallback: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Offline answer (NFR-14): best FAQ match, else a friendly pointer to the builder.
     *
     * @return array{text: string, links: array, source: string}
     */
    private function faqAnswer(string $message, array $context): array
    {
        $faq = $context['faqs']->first() ?? $this->keywordFaq($message);
        $links = $this->contextLinks($context);

        if ($faq) {
            return ['text' => $faq->answer, 'links' => $links, 'source' => 'faq'];
        }

        if ($context['places']->isNotEmpty()) {
            $names = $context['places']->take(3)->pluck('name')->implode(', ');

            return ['text' => "Here are some places that match: {$names}. Open them for photos and details, or start planning your route.", 'links' => $links, 'source' => 'faq'];
        }

        return [
            'text' => "I'm not sure about that one. You can build a day-by-day plan in the Trip Builder, or ask our team on the Contact page.",
            'links' => [['label' => 'Open Trip Builder', 'url' => route('plan', absolute: false)], ['label' => 'Contact us', 'url' => route('contact', absolute: false)]],
            'source' => 'faq',
        ];
    }

    private function keywordFaq(string $message): ?Faq
    {
        $terms = $this->searchTerms($message);

        return Faq::active()->get()
            ->map(fn (Faq $faq) => [$faq, collect($terms)->filter(fn ($t) => Str::contains(Str::lower($faq->question.' '.$faq->keywords), $t))->count()])
            ->filter(fn ($pair) => $pair[1] > 0)
            ->sortByDesc(fn ($pair) => $pair[1])
            ->first()[0] ?? null;
    }

    private function systemPrompt(array $context): string
    {
        $siteMap = collect([
            'Home' => route('home', absolute: false),
            'Destinations' => route('destinations.index', absolute: false),
            'Plan a trip (Trip Builder)' => route('plan', absolute: false),
            'About' => route('about', absolute: false),
            'Contact' => route('contact', absolute: false),
        ])->map(fn ($url, $label) => "- {$label}: {$url}")->implode("\n");

        $lines = [];
        foreach ($context['places'] as $place) {
            $lines[] = "- Place: {$place->name} ({$place->district?->name}) — ".Str::limit((string) $place->short_description, 160).' URL: '.$this->placePath($place);
        }
        foreach ($context['districts'] as $district) {
            $lines[] = "- District: {$district->name} URL: ".route('districts.show', $district, absolute: false);
        }
        foreach ($context['packages'] as $package) {
            $lines[] = "- Package: {$package->name}, {$package->days} days".(Route::has('packages.show') ? ' URL: '.route('packages.show', $package, absolute: false) : '');
        }
        foreach ($context['faqs'] as $faq) {
            $lines[] = "- FAQ: {$faq->question} {$faq->answer}";
        }

        $contextText = $lines ? implode("\n", $lines) : '- (no matching records)';

        return <<<PROMPT
        You are LankaGuide360's travel assistant for Sri Lanka. Answer briefly (under 120 words) and warmly.
        Only recommend places, districts and packages listed in CONTEXT. Never invent places or prices; prices are estimates confirmed by an agent.
        When relevant, add markdown links using only URLs from CONTEXT or SITE MAP, e.g. [Plan a trip](/plan).
        For planning a route, suggest the Trip Builder (/plan). If you are not sure, say so and suggest the Contact page.

        SITE MAP:
        {$siteMap}

        CONTEXT:
        {$contextText}
        PROMPT;
    }

    /**
     * Strip HTML, turn markdown links into buttons, keep only links to our own site.
     *
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    public function sanitise(string $text): array
    {
        $links = [];
        $text = strip_tags($text);

        $text = (string) preg_replace_callback('/\[([^\]]{1,80})\]\(([^)\s]{1,300})\)/', function (array $m) use (&$links) {
            $url = $this->path($m[2]);
            if ($url !== null) {
                $links[] = ['label' => trim($m[1]), 'url' => $url];
            }

            return $m[1];
        }, $text);

        // Drop any remaining bare external URLs.
        $text = (string) preg_replace('~https?://(?!'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost', '~').')\S+~i', '', $text);

        return ['reply' => trim((string) preg_replace('/[ \t]+/', ' ', $text)), 'links' => $links];
    }

    /**
     * Internal path for a site URL, or null for anything external.
     */
    private function path(string $url): ?string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && in_array($host, [parse_url(config('app.url'), PHP_URL_HOST), request()->getHost()], true)) {
            return (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? "?{$q}" : '');
        }

        return null;
    }

    private function placePath(Place $place): string
    {
        return route('destinations.show', [$place->district, $place], absolute: false);
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function contextLinks(array $context): array
    {
        return collect()
            ->concat($context['places']->take(2)->map(fn (Place $p) => ['label' => 'See '.$p->name, 'url' => $this->placePath($p)]))
            ->concat($context['districts']->take(1)->map(fn (District $d) => ['label' => 'Explore '.$d->name, 'url' => route('districts.show', $d, absolute: false)]))
            ->push(['label' => 'Open Trip Builder', 'url' => route('plan', absolute: false)])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $message): array
    {
        $stop = ['the', 'and', 'for', 'are', 'can', 'what', 'where', 'when', 'which', 'how', 'with', 'best', 'good', 'near', 'from', 'about', 'there', 'this', 'that', 'you', 'your', 'have', 'want', 'visit', 'see', 'sri', 'lanka', 'trip', 'place', 'places', 'some', 'any', 'does', 'into'];

        return collect(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($message))) ?: [])
            ->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, $stop, true))
            ->map(fn ($w) => Str::singular($w))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}
