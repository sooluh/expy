<?php

namespace App\Concerns;

use App\Models\RegistrarFee;

trait SyncsRegistrarFees
{
    protected function priceFields(): array
    {
        return [
            'register_price',
            'renew_price',
            'transfer_price',
            'restore_price',
            'privacy_price',
            'misc_price',
        ];
    }

    protected function normalizeFeePayload(array $priceData): array
    {
        $payload = [
            'tld' => $priceData['tld'] ?? null,
        ];

        foreach ($this->priceFields() as $field) {
            if (array_key_exists($field, $priceData)) {
                $payload[$field] = $priceData[$field];
            }
        }

        return $payload;
    }

    protected function feeHasChanges(RegistrarFee $fee, array $payload): bool
    {
        foreach ($this->priceFields() as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if ((string) $fee->$field !== (string) ($payload[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
