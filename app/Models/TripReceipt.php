<?php

namespace App\Models;

use App\Support\Money;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued e-receipt (booking voucher) with a frozen snapshot of the confirmed trip.
 */
#[Fillable([
    'trip_id', 'number', 'verification_code', 'issued_by', 'issued_at', 'revision', 'currency',
    'exchange_rate', 'total_usd', 'paid_usd', 'payment_method', 'payment_note', 'snapshot',
])]
#[Hidden(['verification_code'])]
class TripReceipt extends Model
{
    public const PAYMENT_METHODS = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'online' => 'Online payment', 'other' => 'Other'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revision' => 'integer',
            'exchange_rate' => 'decimal:4',
            'total_usd' => 'decimal:2',
            'paid_usd' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function balanceUsd(): string
    {
        return Money::decimal(max(0, Money::cents($this->total_usd) - Money::cents($this->paid_usd)));
    }

    public function isPaidInFull(): bool
    {
        return Money::cents($this->paid_usd) >= Money::cents($this->total_usd);
    }

    /**
     * A USD amount in the receipt's currency, at the exchange rate frozen when it was issued.
     */
    public function money(string|int|float|null $usd): string
    {
        $cents = Money::cents($usd);
        $converted = $this->currency === 'USD' ? $cents : (int) bcadd(bcmul((string) $cents, (string) $this->exchange_rate, 2), '0.5', 0);

        return Money::format($converted, $this->currency);
    }

    /**
     * Public verification link (encoded in the QR code). Contains no personal data.
     */
    public function verifyUrl(): string
    {
        return route('receipts.verify', ['number' => $this->number, 'code' => $this->verification_code]);
    }

    /**
     * The QR code for verifyUrl() as a PNG data URI (works on screen, in print and in the PDF).
     */
    public function qrCodeDataUri(): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'quietzoneSize' => 2,
            'eccLevel' => EccLevel::M,
        ]);

        return (new QRCode($options))->render($this->verifyUrl());
    }
}
