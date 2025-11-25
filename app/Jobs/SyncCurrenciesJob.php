<?php

namespace App\Jobs;

use App\Models\Currency;
use App\Models\User;
use App\Services\CurrencyapiService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncCurrenciesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(
        private int $userId,
    ) {}

    public function handle(CurrencyapiService $currencyapiService): void
    {
        $user = User::find($this->userId);
        $updatedCount = 0;
        $newCount = 0;

        try {
            $currencies = $currencyapiService->latest();

            if (empty($currencies)) {
                $this->notifyUser($user, 0, 0, 'No currencies returned from API.');

                return;
            }

            $totalCount = count($currencies);

            DB::transaction(function () use ($currencies, &$updatedCount, &$newCount) {
                $existingCurrencies = Currency::query()->pluck('value', 'code')->toArray();

                foreach ($currencies as $currency) {
                    $code = $currency['code'];
                    $newValue = (string) $currency['value'];

                    if (isset($existingCurrencies[$code])) {
                        if ($existingCurrencies[$code] !== $newValue) {
                            Currency::query()
                                ->where('code', $code)
                                ->update([
                                    'value' => $newValue,
                                    'updated_at' => now(),
                                ]);

                            $updatedCount++;
                        }
                    } else {
                        Currency::query()->create([
                            'code' => $code,
                            'value' => $newValue,
                        ]);

                        $newCount++;
                    }
                }
            });

            $this->notifyUser($user, $updatedCount, $newCount, $totalCount);
        } catch (Throwable $exception) {
            $this->notifyUser($user, 0, 0, 0, $exception->getMessage());
        }
    }

    private function notifyUser(User $user, int $updated, int $new, int $total = 0, ?string $error = null): void
    {
        $title = $error ? 'Currency Sync Failed' : 'Currency Sync Completed';

        if ($error) {
            $body = "Failed to synchronize currencies: {$error}";
        } else {
            $parts = ["Successfully synchronized {$total} currencies."];

            if ($new > 0) {
                $parts[] = "{$new} new record".($new > 1 ? 's' : '').' added.';
            }

            if ($updated > 0) {
                $parts[] = "{$updated} existing record".($updated > 1 ? 's' : '').' updated.';
            }

            if ($new === 0 && $updated === 0) {
                $parts[] = 'No changes detected.';
            }

            $body = implode(' ', $parts);
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->status($error ? 'danger' : 'success')
            ->sendToDatabase($user);
    }
}
