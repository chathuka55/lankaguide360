<?php

namespace App\Services\Import;

/**
 * A (possibly cached) response from an external data source.
 */
final class SourceResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly bool $fromCache = false,
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function notFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }
}
