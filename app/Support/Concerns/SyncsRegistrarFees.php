<?php

namespace App\Support\Concerns;

use App\Models\Registrar;
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

    protected function backfillMissingRenewTransfer(Registrar $registrar): void
    {
        $registrar->fees()->chunkById(100, function ($fees) {
            /** @var RegistrarFee $fee */
            foreach ($fees as $fee) {
                $registerPrice = $fee->register_price;
                $renewPrice = $fee->renew_price;
                $transferPrice = $fee->transfer_price;

                if ($registerPrice === null) {
                    continue;
                }

                if ($renewPrice === null || (float) $renewPrice === 0.0) {
                    $fee->renew_price = $registerPrice;
                }

                if ($transferPrice === null || (float) $transferPrice === 0.0) {
                    $fee->transfer_price = $registerPrice;
                }

                if ($fee->isDirty()) {
                    $fee->save();
                }
            }
        });
    }
}
