<?php

namespace App\Services\Reports;

use App\Models\Setting;
use App\Services\ReportService;
use App\Support\Money;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Turns ReportService data into rows and writes them as CSV or Excel (.xlsx). Amounts are
 * exported in USD (as stored) and in the display currency.
 *
 * Text that travellers typed (names, countries) can start with "=", "+", "-" or "@"; it is
 * always written as plain text so spreadsheets never run it as a formula.
 */
class ReportExporter
{
    public function __construct(private ReportService $reports) {}

    /**
     * Rows of one report: each row is column => value (strings, ints, floats or null).
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function rows(string $report): iterable
    {
        $r = $this->reports;
        $currency = Money::displayCurrency();

        return match ($report) {
            'summary' => $this->summaryRows(),
            'monthly' => $r->monthly()->map(fn ($x) => ['Month' => $x['month'], 'Requests' => $x['requests'], 'Booked' => $x['booked'], 'Revenue (USD)' => $x['revenue'], "Revenue ({$currency})" => $this->converted($x['revenue'])]),
            'status' => $r->status()->map(fn ($x) => ['Status' => $x['label'], 'Trips' => $x['trips'], 'Value (USD)' => $x['value'], "Value ({$currency})" => $this->converted($x['value'])]),
            'tiers' => $r->tiers()->map(fn ($x) => ['Travel style' => $x['label'], 'Requests' => $x['trips'], 'Booked' => $x['booked'], 'Revenue (USD)' => $x['revenue'], "Revenue ({$currency})" => $this->converted($x['revenue']), 'Average days' => $x['average_days']]),
            'places' => $r->places(100)->map(fn ($x) => ['Place' => $x['place'], 'District' => $x['district'], 'Trips' => $x['trips'], 'Booked trips' => $x['booked']]),
            'districts' => $r->districts(100)->map(fn ($x) => ['District' => $x['district'], 'Trips' => $x['trips'], 'Stops' => $x['stops']]),
            'hotels' => $r->hotels(100)->map(fn ($x) => ['Hotel' => $x['hotel'], 'Town' => $x['town'], 'Tier' => $x['tier'], 'Nights' => $x['nights'], 'Trips' => $x['trips']]),
            'agents' => $r->agents()->map(fn ($x) => ['Agent' => $x['agent'], 'Role' => $x['role'], 'Assigned' => $x['assigned'], 'Open' => $x['open'], 'Approved' => $x['approved'], 'Rejected' => $x['rejected'], 'Booked' => $x['booked'], 'Revenue (USD)' => $x['revenue'], "Revenue ({$currency})" => $x['revenue'] === null ? null : $this->converted($x['revenue']), 'Avg. first response (hours)' => $x['response_hours']]),
            'countries' => $r->countries(250)->map(fn ($x) => ['Country' => $x['country'], 'Trips' => $x['trips'], 'Travellers' => $x['travellers']]),
            'content' => $r->content()->map(fn ($x) => ['District' => $x['district'], 'Places' => $x['places'], 'Published places' => $x['published_places'], 'Published with location' => $x['routable_places'], 'Hotels' => $x['hotels'], 'Bookable budget hotels' => $x['bookable_budget'], 'Bookable premium hotels' => $x['bookable_premium'], 'Bookable luxury hotels' => $x['bookable_luxury'], 'Ready for the builder' => $x['ready'] ? 'yes' : 'no']),
            'trips' => $r->tripRows()->map(fn ($x) => [
                'Reference' => $x['reference'], 'Submitted' => $x['submitted'], 'Status' => $x['status'], 'Travel style' => $x['tier'],
                'Start date' => $x['start_date'], 'Days' => $x['days'], 'Travellers' => $x['travellers'], 'Lead traveller' => $x['lead_traveller'],
                'Email' => $x['email'], 'Country' => $x['country'], 'Agent' => $x['agent'],
                'Estimate (USD)' => $x['estimate'], "Estimate ({$currency})" => $this->converted($x['estimate']),
                'Final (USD)' => $x['final'], "Final ({$currency})" => $x['final'] === null ? null : $this->converted($x['final']),
            ]),
        };
    }

    public function csv(string $report, string $path): void
    {
        $out = fopen($path, 'w');
        // UTF-8 byte order mark so Excel shows Sinhala and Tamil names correctly.
        fwrite($out, "\xEF\xBB\xBF");
        $header = false;
        foreach ($this->rows($report) as $row) {
            if (! $header) {
                fputcsv($out, array_keys($row));
                $header = true;
            }
            fputcsv($out, array_map(fn ($value) => $this->csvCell($value), array_values($row)));
        }
        fclose($out);
    }

    /**
     * @param  array<int, string>  $reports  one sheet per report
     */
    public function xlsx(array $reports, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->withFontBold(true)->withBackgroundColor('CCFBF1');

        foreach (array_values($reports) as $i => $report) {
            $sheet = $i === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr(ReportService::REPORTS[$report] ?? ucfirst($report), 0, 31));

            $header = false;
            foreach ($this->rows($report) as $row) {
                if (! $header) {
                    $writer->addRow(new Row(array_map(fn ($title) => new StringCell((string) $title, $bold), array_keys($row))));
                    $sheet->setColumnWidth(18, ...range(1, count($row)));
                    $header = true;
                }
                $writer->addRow(new Row(array_map(fn ($value) => $this->xlsxCell($value), array_values($row))));
            }
            if (! $header) {
                $writer->addRow(new Row([new StringCell('No data for this period.')]));
            }
        }

        $writer->close();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryRows(): array
    {
        $s = $this->reports->summary();
        $currency = Money::displayCurrency();
        $money = fn (?string $usd) => $usd === null ? null : $this->converted($usd);

        return [
            ['Measure' => 'Period', 'Value' => $this->reports->from->toDateString().' to '.$this->reports->to->toDateString()],
            ['Measure' => 'Travel style', 'Value' => $this->reports->tier?->label() ?? 'All'],
            ['Measure' => 'Trip requests', 'Value' => $s['requests']],
            ['Measure' => 'Waiting for an agent', 'Value' => $s['open']],
            ['Measure' => 'Approved (or later)', 'Value' => $s['approved']],
            ['Measure' => 'Booked trips', 'Value' => $s['booked']],
            ['Measure' => 'Rejected', 'Value' => $s['rejected']],
            ['Measure' => 'Cancelled', 'Value' => $s['cancelled']],
            ['Measure' => 'Approval rate (%)', 'Value' => $s['approval_rate']],
            ['Measure' => 'Booking rate (%)', 'Value' => $s['booking_rate']],
            ['Measure' => 'Booked revenue (USD)', 'Value' => $s['revenue']],
            ['Measure' => "Booked revenue ({$currency})", 'Value' => $money($s['revenue'])],
            ['Measure' => 'Approved, not yet confirmed (USD)', 'Value' => $s['pipeline']],
            ['Measure' => 'Average booked trip (USD)', 'Value' => $s['average_value']],
            ['Measure' => 'Average trip length (days)', 'Value' => $s['average_days']],
            ['Measure' => 'Travellers', 'Value' => $s['travellers']],
            ['Measure' => 'Requests from guests', 'Value' => $s['guests']],
            ['Measure' => 'Average first response (hours)', 'Value' => $s['response_hours']],
            ['Measure' => 'Exchange rate used', 'Value' => '1 USD = '.Setting::get('usd_lkr', '300').' LKR'],
        ];
    }

    /** USD decimal → display currency decimal string. */
    private function converted(?string $usd): string
    {
        return Money::decimal(Money::convert(Money::cents($usd), Money::displayCurrency()));
    }

    private function csvCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'".$value
            : $value;
    }

    private function xlsxCell(mixed $value): Cell
    {
        return match (true) {
            $value === null || $value === '' => new EmptyCell(null),
            is_int($value) || is_float($value) => new NumericCell($value),
            // Plain decimals from the database (e.g. "1250.00") become real numbers.
            is_string($value) && preg_match('/^-?\d{1,15}(\.\d+)?$/', $value) === 1 => new NumericCell(str_contains($value, '.') ? (float) $value : (int) $value),
            // Everything else is text, never a formula.
            default => new StringCell((string) $value),
        };
    }
}
