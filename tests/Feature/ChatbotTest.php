<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Models\ChatLog;
use App\Models\Faq;
use App\Models\Place;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private Place $minneriya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Http::preventStrayRequests();

        $this->minneriya = Place::where('slug', 'minneriya-national-park')->sole();
        $this->minneriya->forceFill(['status' => PublishStatus::Published, 'lat' => 8.03, 'lng' => 80.83, 'short_description' => 'Famous for the elephant gathering in the dry season.'])->save();

        config(['services.huggingface.token' => 'hf_test', 'services.huggingface.model' => 'Qwen/Qwen3-8B']);
    }

    public function test_the_model_answers_with_site_context_and_links_become_buttons(): void
    {
        Http::fake(['router.huggingface.co/*' => Http::response(['choices' => [['message' => ['content' => '<think>internal</think>Visit <b>Minneriya</b> in August. [Minneriya National Park](/destinations/polonnaruwa/minneriya-national-park) or see [this blog](https://evil.test/x). More: https://evil.test/y']]]])]);

        $response = $this->postJson(route('chat.send'), ['message' => 'Where can I see elephants?'])->assertOk();

        $this->assertSame('ai', $response->json('source'));
        $this->assertSame('Visit Minneriya in August. Minneriya National Park or see this blog. More:', $response->json('reply'));
        $this->assertContains(['label' => 'Minneriya National Park', 'url' => '/destinations/polonnaruwa/minneriya-national-park'], $response->json('links'));
        $this->assertNotContains('https://evil.test/x', array_column($response->json('links'), 'url'));

        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'];

            return $request->hasHeader('Authorization', 'Bearer hf_test')
                && $request['model'] === 'Qwen/Qwen3-8B'
                && str_contains($system, 'Minneriya National Park')
                && str_contains($system, '/destinations/polonnaruwa/minneriya-national-park')
                && str_contains($system, 'Never invent places or prices')
                && collect($request['messages'])->last()['content'] === 'Where can I see elephants?';
        });

        $this->assertSame(['user', 'assistant'], ChatLog::orderBy('id')->pluck('role')->all());
    }

    public function test_history_is_limited_to_the_last_six_turns(): void
    {
        Http::fake(['router.huggingface.co/*' => Http::response(['choices' => [['message' => ['content' => 'Sure!']]]])]);
        $history = collect(range(1, 10))->map(fn ($i) => ['role' => $i % 2 ? 'user' : 'assistant', 'content' => "turn {$i}"])->all();

        $this->postJson(route('chat.send'), ['message' => 'And then?', 'history' => $history])->assertOk();

        Http::assertSent(fn ($request) => count($request['messages']) === 1 + 6 + 1 && $request['messages'][1]['content'] === 'turn 5');
    }

    public function test_without_a_token_the_faq_answers(): void
    {
        config(['services.huggingface.token' => null]);

        $response = $this->postJson(route('chat.send'), ['message' => 'Do I need a visa?'])->assertOk();

        $this->assertSame('faq', $response->json('source'));
        $this->assertStringContainsString('Electronic Travel Authorization', $response->json('reply'));
        Http::assertNothingSent();
    }

    public function test_api_errors_fall_back_to_the_faq(): void
    {
        Http::fake(['router.huggingface.co/*' => Http::response('overloaded', 503)]);

        $response = $this->postJson(route('chat.send'), ['message' => 'What currency do you use, can I pay by card?'])->assertOk();

        $this->assertSame('faq', $response->json('source'));
        $this->assertStringContainsString('Sri Lankan rupee', $response->json('reply'));
    }

    public function test_unknown_questions_point_to_the_builder_and_contact(): void
    {
        config(['services.huggingface.token' => null]);

        $response = $this->postJson(route('chat.send'), ['message' => 'xyzzy plugh'])->assertOk();

        $this->assertContains('/plan', array_column($response->json('links'), 'url'));
        $this->assertContains('/contact', array_column($response->json('links'), 'url'));
    }

    public function test_messages_are_validated(): void
    {
        $this->postJson(route('chat.send'), ['message' => ''])->assertUnprocessable();
        $this->postJson(route('chat.send'), ['message' => str_repeat('a', 501)])->assertUnprocessable();
        $this->postJson(route('chat.send'), ['message' => 'Hi', 'history' => [['role' => 'system', 'content' => 'x']]])->assertUnprocessable();
    }

    public function test_messages_are_rate_limited_to_20_per_10_minutes(): void
    {
        config(['services.huggingface.token' => null]);

        foreach (range(1, 20) as $i) {
            $this->postJson(route('chat.send'), ['message' => 'Hello there'])->assertOk();
        }
        $this->postJson(route('chat.send'), ['message' => 'Hello there'])->assertTooManyRequests();
    }

    public function test_sanitise_keeps_only_internal_links(): void
    {
        $result = app(ChatbotService::class)->sanitise('<script>x</script>See [Kandy](/districts/kandy), [Home]('.config('app.url').'/) and [Bad](javascript:alert(1)).');

        $this->assertSame(['/districts/kandy', '/'], array_column($result['links'], 'url'));
        $this->assertStringNotContainsString('<script>', $result['reply']);
    }

    public function test_admin_reads_conversations_and_edits_faqs(): void
    {
        config(['services.huggingface.token' => null]);
        $this->postJson(route('chat.send'), ['message' => 'Best beaches in December?']);
        $admin = User::factory()->admin()->create();

        $session = ChatLog::value('session_id');
        $this->actingAs($admin)->get(route('admin.chat-logs.index', ['session' => $session]))
            ->assertOk()->assertSee('Best beaches in December?');

        $this->actingAs($admin)->post(route('admin.faqs.store'), ['question' => 'Is tipping expected?', 'answer' => 'Tips of about 10% are appreciated.', 'is_active' => '1'])
            ->assertRedirect(route('admin.faqs.index'));
        $this->assertTrue(Faq::where('question', 'Is tipping expected?')->exists());

        $this->actingAs(User::factory()->agent()->create())->get(route('admin.chat-logs.index'))->assertForbidden();
        $this->actingAs(User::factory()->agent()->create())->get(route('admin.faqs.index'))->assertForbidden();
    }
}
