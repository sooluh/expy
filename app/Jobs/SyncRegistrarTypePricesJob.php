<?php

namespace App\Jobs;

use App\Concerns\SyncsRegistrarFees;
use App\Models\Registrar;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRegistrarTypePricesJob implements ShouldQueue
{
    use Queueable, SyncsRegistrarFees;

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

                if (! $payload['tld']) {
                    continue;
                }

                $fee = $registrar->fees()->where('tld', $payload['tld'])->first();

                if ($fee) {
                    if ($this->feeHasChanges($fee, $payload)) {
                        $fee->update($payload);
                    }
                } else {
                    if ($renewPrice === null || $renewPrice <= 0) {
                        continue;
                    }

                    if (! array_key_exists('register_price', $payload)) {
                        $payload['register_price'] = $renewPrice;
                    }

                    $registrar->fees()->create($payload);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to sync registrar type prices', [
                'registrar_id' => $this->registrarId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
