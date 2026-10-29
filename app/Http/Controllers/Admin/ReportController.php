<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Tier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\Reports\ReportExporter;
use App\Services\ReportService;
use App\Services\TripPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin reports (requests, conversion, revenue, destinations, hotels, agents, data readiness),
 * exported as CSV or Excel per report, as one Excel workbook, or as a printable PDF.
 */
class ReportController extends Controller
{
    public const FORMATS = ['csv', 'xlsx'];

    public function index(ReportFilterRequest $request): View
    {
        $reports = $request->service();

        return view('admin.reports.index', [
            ...$this->data($reports),
            'filters' => ['from' => $reports->from->toDateString(), 'to' => $reports->to->toDateString(), 'tier' => $reports->tier?->value],
            'tiers' => collect(Tier::cases())->mapWithKeys(fn (Tier $t) => [$t->value => $t->label()])->all(),
        ]);
    }

    public function pdf(ReportFilterRequest $request, TripPdf $pdf): Response
    {
        abort_unless($pdf->available(), 503, 'PDF export needs barryvdh/laravel-dompdf (composer install).');
        $reports = $request->service();

        return Pdf::loadView('admin.reports.pdf', $this->data($reports))
            ->setPaper('a4')
            ->download($this->filename($reports, 'report', 'pdf'));
    }

    /**
     * One report as CSV (?format=csv, the default) or Excel (?format=xlsx).
     */
    public function export(ReportFilterRequest $request, string $report): BinaryFileResponse
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);
        $format = in_array($request->query('format'), self::FORMATS, true) ? $request->query('format') : 'csv';
        $reports = $request->service();
        $exporter = new ReportExporter($reports);
        $path = $this->tempFile($format);

        $format === 'xlsx' ? $exporter->xlsx([$report], $path) : $exporter->csv($report, $path);

        return response()->download($path, $this->filename($reports, $report, $format), [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend();
    }

    /**
     * Every report in one Excel workbook (one sheet each, Summary first) for analysis.
     */
    public function workbook(ReportFilterRequest $request): BinaryFileResponse
    {
        $reports = $request->service();
        $path = $this->tempFile('xlsx');

        (new ReportExporter($reports))->xlsx(['summary', ...array_keys(ReportService::REPORTS)], $path);

        return response()->download($path, $this->filename($reports, 'analytics', 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ReportService $reports): array
    {
        return [
            'reports' => $reports,
            'summary' => $reports->summary(),
            'monthly' => $reports->monthly(),
            'status' => $reports->status(),
            'tierRows' => $reports->tiers(),
            'places' => $reports->places(),
            'districts' => $reports->districts(10),
            'hotels' => $reports->hotels(10),
            'agents' => $reports->agents(),
            'countries' => $reports->countries(10),
            'content' => $reports->content(),
        ];
    }

    private function filename(ReportService $reports, string $name, string $extension): string
    {
        return "lankaguide360-{$name}-{$reports->from->toDateString()}-to-{$reports->to->toDateString()}.{$extension}";
    }

    private function tempFile(string $extension): string
    {
        $dir = storage_path('app/private/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir.'/'.bin2hex(random_bytes(8)).'.'.$extension;
    }
}
