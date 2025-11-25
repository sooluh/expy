<?php

namespace App\Jobs;

use App\Models\Registrar;
use App\Models\User;
use App\Support\Concerns\SyncsRegistrarFees;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRegistrarPricesJob implements ShouldQueue
{
    use Queueable, SyncsRegistrarFees;

    public int $timeout = 180;

    public function __construct(
        public int $registrarId,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $registrar = Registrar::find($this->registrarId);

        if (! $registrar) {
            $this->sendNotification('Registrar not found', 'danger');

            return;
        }

        if (! $registrar->hasApiSupport()) {
            $this->sendNotification("{$registrar->name} does not have API support", 'warning');

            return;
        }

        $service = $registrar->getService();

        if (! $service || ! $service->isConfigured()) {
            $this->sendNotification("{$registrar->name} API is not properly configured", 'danger');

            return;
        }

        try {
            $prices = $service->getPrices();
            $newCount = 0;
            $updatedCount = 0;

            foreach ($prices as $priceData) {
                $payload = $this->normalizeFeePayload($priceData);

                if (! $payload['tld']) {
                    continue;
                }

                $fee = $registrar->fees()->where('tld', $payload['tld'])->first();

                if ($fee) {
                    if ($this->feeHasChanges($fee, $payload)) {
                        $fee->update($payload);
                        $updatedCount++;
                    }
                } else {
                    $registrar->fees()->create($payload);
                    $newCount++;
                }
            }

            if ($service->supportsDeferredPriceSync()) {
                $service->dispatchDeferredPriceSync($this->userId);
            }

            $message = "{$registrar->name}: {$newCount} new TLD(s), {$updatedCount} updated";
            $this->sendNotification($message, 'success');

            $registrar->forceFill(['last_sync_at' => now()])->save();
        } catch (Exception $e) {
            Log::error('Failed to sync registrar prices', [
                'registrar_id' => $this->registrarId,
                'error' => $e->getMessage(),
            ]);

            $this->sendNotification("Failed to sync {$registrar->name}: {$e->getMessage()}", 'danger');
        }
    }

    protected function sendNotification(string $message, string $status): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Registrar Price Sync')
            ->body($message)
            ->status($status)
            ->sendToDatabase($user);
    }
}
