<?php

namespace App\Services\Pricing;

use App\Enums\PriceCategory;
use App\Support\Money;

/**
 * Itemised trip price (SRS 5.4, FR-16). Amounts are integer cents.
 */
final class PriceBreakdown
{
    /** @var array<int, array{category: PriceCategory, description: string, qty: float, unit_cents: int, amount_cents: int}> */
    public array $lines = [];

    public int $subtotal = 0;

    public int $serviceFee = 0;

    public int $tax = 0;

    public int $total = 0;

    public int $perPerson = 0;

    public int $totalLkr = 0;

    /** @var array<int, string> */
    public array $notes = [];

    public function __construct(public string $currency = 'USD', public int $payingTravellers = 1) {}

    public function add(PriceCategory $category, string $description, float $qty, int $unitCents): void
    {
        $amount = (int) round($qty * $unitCents);

        // Zero lines are kept on purpose, e.g. "hotel to be confirmed".
        $this->lines[] = [
            'category' => $category,
            'description' => $description,
            'qty' => $qty,
            'unit_cents' => $unitCents,
            'amount_cents' => $amount,
        ];
    }

    /**
     * Subtotal of one category.
     */
    public function categoryTotal(PriceCategory $category): int
    {
        return array_sum(array_column(array_filter($this->lines, fn ($l) => $l['category'] === $category), 'amount_cents'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $groups = [];
        foreach ($this->lines as $line) {
            $groups[$line['category']->value] = ($groups[$line['category']->value] ?? 0) + $line['amount_cents'];
        }

        return [
            'currency' => $this->currency,
            'lines' => array_map(fn ($line) => [
                'category' => $line['category']->value,
                'description' => $line['description'],
                'qty' => $line['qty'],
                'unit_price' => Money::decimal($line['unit_cents']),
                'amount' => Money::decimal($line['amount_cents']),
            ], $this->lines),
            'categories' => array_map(fn (int $cents) => Money::decimal($cents), $groups),
            'subtotal' => Money::decimal($this->subtotal),
            'service_fee' => Money::decimal($this->serviceFee),
            'tax' => Money::decimal($this->tax),
            'total' => Money::decimal($this->total),
            'per_person' => Money::decimal($this->perPerson),
            'total_lkr' => Money::decimal($this->totalLkr),
            'paying_travellers' => $this->payingTravellers,
            'notes' => $this->notes,
        ];
    }
}
