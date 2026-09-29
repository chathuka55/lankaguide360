<?php

namespace App\Services\Import;

/**
 * Wikimedia Commons file search, shared by the Commons importer and the admin media manager.
 * Results are normalized and assessed against the site's license and size rules.
 */
class CommonsClient
{
    /** Titles that are almost never a photo of the place itself. */
    private const SKIP_TITLE = '/\b(map|maps|locator|location|logo|flag|coat of arms|emblem|seal|diagram|plan|chart|stamp|banknote|coin|icon|poster|signature|drawing)\b/i';

    private const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    private const IMAGEINFO = [
        'prop' => 'imageinfo',
        'iiprop' => 'url|size|mime|extmetadata',
        'iiurlwidth' => 1600,
        'iiextmetadatafilter' => 'Artist|Credit|LicenseShortName|LicenseUrl|ImageDescription|ObjectName',
    ];

    public function __construct(private ImportClient $client) {}

    /**
     * Only freely reusable licenses: CC0, CC BY, CC BY-SA (any version or port) and public domain.
     * NC/ND licenses, GFDL-only and unknown licenses are rejected.
     */
    public static function acceptsLicense(?string $shortName): bool
    {
        $license = strtolower(trim((string) $shortName));

        if ($license === '' || preg_match('/\b(nc|nd)\b/', $license)) {
            return false;
        }

        return (bool) preg_match('/^(cc0( 1\.0)?|cc[ -]zero|cc by(-sa)?( \d(\.\d)?)?( [a-z]{2,3})?|public domain( mark)?|pd(-[a-z0-9-]+)?)$/', $license);
    }

    /**
     * Search the File namespace (bitmaps only).
     *
     * @return array<int, array<string, mixed>> normalized candidates in relevance order
     */
    public function search(string $query, int $limit = 20): array
    {
        $response = $this->client->get(config('lankaguide.import.commons_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'generator' => 'search',
            'gsrsearch' => $query.' filetype:bitmap',
            'gsrnamespace' => 6,
            'gsrlimit' => $limit,
            ...self::IMAGEINFO,
        ], $this->options());

        if (! $response->ok()) {
            throw new ImportException("Commons search returned HTTP {$response->status}.");
        }

        return collect($response->json()['query']['pages'] ?? [])
            ->sortBy('index')
            ->map(fn (array $page) => $this->normalize($page))
            ->values()
            ->all();
    }

    /**
     * Look up specific files, e.g. the ones an admin ticked in the search modal.
     *
     * @param  array<int, string>  $titles  e.g. ["File:Sigiriya 02.jpg"]
     * @return array<int, array<string, mixed>>
     */
    public function files(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $response = $this->client->get(config('lankaguide.import.commons_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'titles' => implode('|', array_slice($titles, 0, 50)),
            ...self::IMAGEINFO,
        ], $this->options());

        if (! $response->ok()) {
            throw new ImportException("Commons lookup returned HTTP {$response->status}.");
        }

        return collect($response->json()['query']['pages'] ?? [])
            ->filter(fn (array $page) => isset($page['imageinfo']))
            ->map(fn (array $page) => $this->normalize($page))
            ->values()
            ->all();
    }

    /**
     * Why a candidate can't be used (type, size, title, license), or null if it can.
     */
    public function assess(array $candidate): ?string
    {
        return match (true) {
            ! in_array($candidate['mime'], self::ACCEPTED_MIME, true) => 'type',
            $candidate['width'] < config('lankaguide.import.min_image_width') => 'size',
            (bool) preg_match(self::SKIP_TITLE, $candidate['title']) => 'title',
            ! self::acceptsLicense($candidate['license']) => 'license',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $page): array
    {
        $info = $page['imageinfo'][0] ?? [];
        $meta = $info['extmetadata'] ?? [];
        $value = fn (string $key) => $meta[$key]['value'] ?? null;

        $candidate = [
            'title' => $page['title'] ?? '',
            'description_url' => $info['descriptionurl'] ?? null,
            'thumb_url' => $info['thumburl'] ?? $info['url'] ?? null,
            'width' => (int) ($info['width'] ?? 0),
            'height' => (int) ($info['height'] ?? 0),
            'mime' => $info['mime'] ?? '',
            'license' => $value('LicenseShortName'),
            'license_url' => $value('LicenseUrl'),
            'author' => self::plainText($value('Artist')) ?: self::plainText($value('Credit')),
            'description' => self::plainText($value('ImageDescription') ?? $value('ObjectName')),
        ];

        $candidate['rejected_because'] = $this->assess($candidate);

        return $candidate;
    }

    /**
     * Plain text from Commons HTML fragments.
     */
    public static function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * @return array{group: string, delay_ms: int}
     */
    private function options(): array
    {
        return ['group' => 'wikimedia', 'delay_ms' => (int) config('lankaguide.import.delay_ms.wikimedia')];
    }
}
