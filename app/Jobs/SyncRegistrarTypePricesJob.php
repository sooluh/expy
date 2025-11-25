<?php

namespace App\Jobs;

use App\Models\Registrar;
use App\Support\Concerns\NotifiesJobFailure;
use App\Support\Concerns\SyncsRegistrarFees;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRegistrarTypePricesJob implements ShouldQueue
{
    use NotifiesJobFailure, Queueable, SyncsRegistrarFees;

    public int $timeout = 180;

    public function __construct(
        public int $registrarId,
        public string $type,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $registrar = Registrar::find($this->registrarId);

        if (! $registrar) {
            Log::warning('Registrar not found for type price sync', [
                'registrar_id' => $this->registrarId,
                'type' => $this->type,
            ]);

            return;
        }

        if (! $registrar->hasApiSupport()) {
            return;
        }

        $service = $registrar->getService();

        if (! $service || ! method_exists($service, 'getPricesByType')) {
            return;
        }

        if (method_exists($service, 'isConfigured') && ! $service->isConfigured()) {
            return;
        }

        try {
            $prices = $service->getPricesByType($this->type);

            foreach ($prices as $priceData) {
                $payload = $this->normalizeFeePayload($priceData);
                $renewPrice = $payload['renew_price'] ?? null;
                $registerPrice = $payload['register_price'] ?? null;

                if (! $payload['tld']) {
                    continue;
                }

                $fee = $registrar->fees()->where('tld', $payload['tld'])->first();

                if ($fee) {
                    $registerPrice ??= $fee->register_price;

                    if (($renewPrice === null || $renewPrice <= 0) && $registerPrice !== null) {
                        $payload['renew_price'] = $registerPrice;
                        $payload['transfer_price'] = $payload['transfer_price'] ?? $registerPrice;
                    }

                    if ($this->feeHasChanges($fee, $payload)) {
                        $fee->update($payload);
                    }
                } else {
                    if ($renewPrice === null || $renewPrice <= 0) {
                        continue;
                    }

                    $payload['register_price'] = $registerPrice ?? $renewPrice;
                    $payload['transfer_price'] = $payload['transfer_price'] ?? $payload['register_price'];
                    $payload['renew_price'] = $payload['renew_price'] ?? $payload['register_price'];

                    $registrar->fees()->create($payload);
                }
            }

            $this->backfillMissingRenewTransfer($registrar);
        } catch (Exception $e) {
            Log::error('Failed to sync registrar type prices', [
                'registrar_id' => $this->registrarId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            $this->notifyFailure(
                $this->userId,
                'Registrar Price Sync Failed',
                "Failed to sync {$this->type} prices: {$e->getMessage()}"
            );
        }
    }
}
