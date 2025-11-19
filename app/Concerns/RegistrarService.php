<?php

namespace App\Concerns;

use App\Enums\RegistrarCode;
use App\Models\Currency;
use App\Settings\GeneralSettings;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait RegistrarService
{
    protected static function resolveApiSupport(mixed $value): RegistrarCode
    {
        if ($value instanceof RegistrarCode) {
            return $value;
        }

        if (is_string($value)) {
            return RegistrarCode::tryFrom($value) ?? RegistrarCode::NONE;
        }

        return RegistrarCode::NONE;
    }

    protected static function requiresApiKey(mixed $apiSupport): bool
    {
        $support = static::resolveApiSupport($apiSupport);

        return match ($support) {
            RegistrarCode::DYNADOT => true,
            RegistrarCode::PORKBUN => true,
            RegistrarCode::NONE => false,
            default => false,
        };
    }

    protected function getApiKey(): ?string
    {
        $apiSettings = $this->registrar->api_settings;

        if (empty($apiSettings['api_key'])) {
            return null;
        }

        try {
            return Crypt::decryptString($apiSettings['api_key']);
        } catch (Exception $e) {
            Log::error('Failed to decrypt API key', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function getSecretKey(): ?string
    {
        $apiSettings = $this->registrar->api_settings;

        if (empty($apiSettings['secret_key'])) {
            return null;
        }

        try {
            return Crypt::decryptString($apiSettings['secret_key']);
        } catch (Exception $e) {
            Log::error('Failed to decrypt secret key', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function getCookies(): ?string
    {
        $apiSettings = $this->registrar->api_settings;

        if (empty($apiSettings['cookies'])) {
            return null;
        }

        try {
            return Crypt::decryptString($apiSettings['cookies']);
        } catch (Exception $e) {
            Log::error('Failed to decrypt cookies', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function parsePrice(?string $price): ?float
    {
        if ($price === null || $price === '' || $price === '0.00') {
            return null;
        }

        $usdPrice = (float) $price;

        return $usdPrice > 0 ? $usdPrice : null;
    }

    protected function getCurrencyRate(): float
    {
        try {
            $settings = app(GeneralSettings::class);
            $currencyCode = $settings->currency;

            if ($currencyCode === 'USD') {
                return 1.0;
            }

            $currency = Currency::where('code', $currencyCode)->first();

            return $currency ? (float) $currency->value : 1.0;
        } catch (Exception $e) {
            Log::warning('Failed to get currency rate, using 1.0', [
                'error' => $e->getMessage(),
            ]);

            return 1.0;
        }
    }
}
