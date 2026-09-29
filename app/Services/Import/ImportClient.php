<?php

namespace App\Services\Import;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * The only way importers talk to external services (CLAUDE.md architecture rules):
 * descriptive User-Agent, timeout, retries on 429/5xx and connection errors, a minimum
 * delay between network requests to the same service, and a raw-response cache on disk
 * so re-runs don't re-download.
 */
class ImportClient
{
    /** Serve responses from the on-disk cache when available. */
    public bool $useCache = true;

    /** @var array<string, float> microtime of the last network request per throttle group */
    private array $lastRequestAt = [];

    /**
     * @param  array<string, mixed>  $query
     * @param  array{delay_ms?: int, group?: string, backoff_ms?: array<int, int>}  $options
     */
    public function get(string $url, array $query = [], array $options = []): SourceResponse
    {
        return $this->send('GET', $url, $query, $options);
    }

    /**
     * Form-encoded POST (used for Overpass queries).
     *
     * @param  array<string, mixed>  $form
     * @param  array{delay_ms?: int, group?: string, backoff_ms?: array<int, int>}  $options
     */
    public function post(string $url, array $form, array $options = []): SourceResponse
    {
        return $this->send('POST', $url, $form, $options);
    }

    /**
     * Download a binary file (images). Not cached: media rows record what was imported.
     *
     * @param  array{delay_ms?: int, group?: string}  $options
     */
    public function download(string $url, array $options = []): string
    {
        $this->throttle($options['group'] ?? 'download', $options['delay_ms'] ?? 0);

        try {
            $response = $this->request()->get($url);
        } catch (Throwable $e) {
            throw new ImportException("Download failed for {$url}: {$e->getMessage()}", previous: $e);
        } finally {
            $this->lastRequestAt[$options['group'] ?? 'download'] = microtime(true);
        }

        if (! $response->successful()) {
            throw new ImportException("Download failed for {$url}: HTTP {$response->status()}");
        }

        return $response->body();
    }

    public function cachePath(string $method, string $url, array $data): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        $key = sha1($method.' '.$url.'?'.http_build_query($data));

        return rtrim(config('lankaguide.import.cache_path'), '/\\').DIRECTORY_SEPARATOR.$host.DIRECTORY_SEPARATOR.$key.'.json';
    }

    private function send(string $method, string $url, array $data, array $options): SourceResponse
    {
        $cacheFile = $this->cachePath($method, $url, $data);

        if ($this->useCache && File::exists($cacheFile)) {
            $cached = json_decode(File::get($cacheFile), true);

            if (is_array($cached) && isset($cached['status'], $cached['body'])) {
                return new SourceResponse($cached['status'], $cached['body'], fromCache: true);
            }
        }

        $group = $options['group'] ?? (parse_url($url, PHP_URL_HOST) ?: 'default');
        $this->throttle($group, $options['delay_ms'] ?? 0);

        try {
            $request = $this->request($options['backoff_ms'] ?? null);
            $response = $method === 'POST' ? $request->asForm()->post($url, $data) : $request->get($url, $data);
        } catch (ConnectionException $e) {
            throw new ImportException("Could not reach {$url}: {$e->getMessage()}", previous: $e);
        } finally {
            $this->lastRequestAt[$group] = microtime(true);
        }

        $result = new SourceResponse($response->status(), $response->body());

        // Cache successes and definite "not found" answers; never cache transient errors.
        if ($result->ok() || $result->notFound()) {
            File::ensureDirectoryExists(dirname($cacheFile));
            File::put($cacheFile, json_encode([
                'status' => $result->status,
                'url' => $url,
                'fetched_at' => now()->toIso8601String(),
                'body' => $result->body,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $result;
    }

    /**
     * @param  array<int, int>|null  $backoffMs  sleep before each retry, e.g. [5000, 15000, 30000]
     */
    private function request(?array $backoffMs = null): PendingRequest
    {
        $retries = (int) config('lankaguide.import.retries', 3);

        return Http::withUserAgent(config('lankaguide.user_agent'))
            ->acceptJson()
            ->timeout((int) config('lankaguide.import.timeout', 20))
            ->retry(
                $backoffMs ?? array_fill(0, max(0, $retries - 1), (int) config('lankaguide.import.retry_sleep_ms', 2000)),
                when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true)),
                throw: false,
            );
    }

    private function throttle(string $group, int $delayMs): void
    {
        if ($delayMs <= 0 || ! isset($this->lastRequestAt[$group])) {
            return;
        }

        $waitMs = (int) ceil($delayMs - (microtime(true) - $this->lastRequestAt[$group]) * 1000);

        if ($waitMs > 0) {
            Sleep::for($waitMs)->milliseconds();
        }
    }
}
