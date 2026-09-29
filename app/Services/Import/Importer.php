<?php

namespace App\Services\Import;

use App\Models\ImportLog;
use App\Services\Import\Progress\ImportProgress;
use App\Services\Import\Progress\NullProgress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base class for the lg:import-* importers. Every run writes import_logs rows so admins can
 * review what changed; imported rows are always drafts (CLAUDE.md data rules).
 */
abstract class Importer
{
    protected ImportReport $report;

    protected ImportProgress $progress;

    public function __construct(protected ImportClient $client) {}

    /** Short name stored in import_logs.importer, e.g. "wikipedia". */
    abstract public function key(): string;

    /**
     * @param  array<string, mixed>  $options  always supports `fresh` (bypass the response cache)
     */
    public function run(array $options = [], ?ImportProgress $progress = null): ImportReport
    {
        $this->report = new ImportReport($this->key());
        $this->progress = $progress ?? new NullProgress;
        $this->client->useCache = ! ($options['fresh'] ?? false);

        $this->handle($options);

        $this->log('info', null, $this->report->summary());

        return $this->report;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    abstract protected function handle(array $options): void;

    protected function log(string $status, ?Model $target, string $message): void
    {
        ImportLog::create([
            'importer' => $this->key(),
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'status' => $status,
            'message' => Str::limit($message, 2000),
        ]);
    }

    /**
     * Plain text from Commons/Wikipedia HTML fragments.
     */
    protected function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
