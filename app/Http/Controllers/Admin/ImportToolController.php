<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunImportRequest;
use App\Jobs\RunImport;
use App\Models\ImportLog;
use App\Services\Import\DistrictGeometry;
use App\Services\Import\ImporterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Run importers as queued jobs and read their logs (admin only).
 */
class ImportToolController extends Controller
{
    public function index(Request $request, DistrictGeometry $geometry): View
    {
        Gate::authorize('admin');

        $logKeys = array_column(ImporterRegistry::DETAILS, 'log');

        $importers = collect(ImporterRegistry::DETAILS)->map(fn (array $details, string $name) => $details + [
            'name' => $name,
            'last_run' => ImportLog::where('importer', $details['log'])->where('status', 'info')->latest('id')->first(),
        ]);

        $logs = ImportLog::query()
            ->with('target')
            ->when($request->query('importer'), fn ($q, $key) => $q->where('importer', $key))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.imports.index', [
            'importers' => $importers,
            'logs' => $logs,
            'logKeys' => array_combine($logKeys, $logKeys),
            'statuses' => ImportLog::distinct()->orderBy('status')->pluck('status', 'status'),
            'pendingJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'hasBoundaries' => $geometry->exists(),
        ]);
    }

    public function run(RunImportRequest $request): RedirectResponse
    {
        $importer = $request->validated('importer');

        RunImport::dispatch($importer, $request->importOptions());

        return back()->with('status', ImporterRegistry::DETAILS[$importer]['label'].' import queued. It runs when the queue worker (php artisan queue:work) picks it up.');
    }

    public function runAll(RunImportRequest $request): RedirectResponse
    {
        Bus::chain(array_map(
            fn (string $name) => new RunImport($name, ['fresh' => $request->boolean('fresh')]),
            ImporterRegistry::ORDER,
        ))->dispatch();

        return back()->with('status', 'All importers queued in order. Keep php artisan queue:work running; this can take a while.');
    }
}
