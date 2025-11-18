<?php

namespace App\Models;

use App\Settings\GeneralSettings;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrarFee extends Model
{
    protected $fillable = [
        'registrar_id',
        'tld',
        'register_price',
        'renew_price',
        'transfer_price',
        'restore_price',
        'privacy_price',
        'misc_price',
    ];

    protected $casts = [
        'register_price' => 'decimal:2',
        'renew_price' => 'decimal:2',
        'transfer_price' => 'decimal:2',
        'restore_price' => 'decimal:2',
        'privacy_price' => 'decimal:2',
        'misc_price' => 'decimal:2',
    ];

    protected $appends = [
        'register_price_converted',
        'renew_price_converted',
        'transfer_price_converted',
        'restore_price_converted',
        'privacy_price_converted',
        'misc_price_converted',
    ];

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    protected function getCurrencyRate(): float
    {
        try {
            /** @var GeneralSettings $settings */
            $settings = app(GeneralSettings::class);
            $displayCode = $settings->currency;
            $registrarCode = $this->registrar?->currency?->code ?? $displayCode;

            if ($registrarCode === $displayCode) {
                return 1.0;
            }

            $baseRate = $this->getRatePerUsd($registrarCode);
            $targetRate = $this->getRatePerUsd($displayCode);

            if ($baseRate <= 0) {
                return 1.0;
            }

            return $targetRate / $baseRate;
        } catch (Exception) {
            return 1.0;
        }
    }

    protected function getRatePerUsd(string $code): float
    {
        if ($code === 'USD') {
            return 1.0;
        }

        $currency = Currency::where('code', $code)->first();

        return $currency ? (float) $currency->value : 1.0;
    }

    protected function convertPrice(?float $storedPrice): ?float
    {
        if ($storedPrice === null) {
            return null;
        }

        return $storedPrice * $this->getCurrencyRate();
    }

    protected function registerPriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->register_price),
        );
    }

    protected function renewPriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->renew_price),
        );
    }

    protected function transferPriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->transfer_price),
        );
    }

    protected function restorePriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->restore_price),
        );
    }

    protected function privacyPriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->privacy_price),
        );
    }

    protected function miscPriceConverted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->convertPrice($this->misc_price),
        );
    }
}
