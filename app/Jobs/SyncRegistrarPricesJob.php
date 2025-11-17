<?php

namespace App\Jobs;

use App\Models\Registrar;
use App\Models\User;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRegistrarPricesJob implements ShouldQueue
{
    use Queueable;

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
                $fee = $registrar->fees()->where('tld', $priceData['tld'])->first();

                if ($fee) {
                    $changed = false;
                    foreach (['register_price', 'renew_price', 'transfer_price', 'restore_price', 'privacy_price', 'misc_price'] as $field) {
                        if ((string) $fee->$field !== (string) ($priceData[$field] ?? null)) {
                            $changed = true;
                            break;
                        }
                    }

                    if ($changed) {
                        $fee->update($priceData);
                        $updatedCount++;
                    }
                } else {
                    $registrar->fees()->create($priceData);
                    $newCount++;
                }
            }

            $message = "{$registrar->name}: {$newCount} new TLD(s), {$updatedCount} updated";
            $this->sendNotification($message, 'success');
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
