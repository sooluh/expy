<?php

namespace App\Jobs;

use App\Models\Rdap;
use App\Models\User;
use App\Services\IanaService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncRdapsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(
        private int $userId,
    ) {}

    public function handle(IanaService $ianaService): void
    {
        $user = User::find($this->userId);
        $updatedCount = 0;
        $newCount = 0;

        try {
            $services = $ianaService->fetchRdapServices();

            if (empty($services)) {
                $this->notifyUser($user, 0, 0, 'No RDAP services returned from IANA.');

                return;
            }

            $totalCount = count($services);

            DB::transaction(function () use ($services, &$updatedCount, &$newCount) {
                $existingRdaps = Rdap::query()->pluck('rdap', 'tld')->toArray();

                foreach ($services as $service) {
                    $tld = $service['tld'];
                    $rdapUrl = $service['rdap'];

                    if (isset($existingRdaps[$tld])) {
                        if ($existingRdaps[$tld] !== $rdapUrl) {
                            Rdap::query()
                                ->where('tld', $tld)
                                ->update([
                                    'rdap' => $rdapUrl,
                                    'updated_at' => now(),
                                ]);

                            $updatedCount++;
                        }
                    } else {
                        Rdap::query()->create([
                            'tld' => $tld,
                            'rdap' => $rdapUrl,
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
        $title = $error ? 'RDAP Sync Failed' : 'RDAP Sync Completed';

        if ($error) {
            $body = "Failed to sync RDAP data: {$error}";
        } else {
            $parts = ["Successfully synchronized {$total} RDAP records."];

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
