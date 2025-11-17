<?php

namespace App\Filament\Resources\Currencies\Pages;

use App\Filament\Resources\Currencies\CurrencyResource;
use App\Jobs\SyncCurrenciesJob;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageCurrencies extends ManageRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('synchronize')
                ->label('Synchronize')
                ->requiresConfirmation()
                ->modalHeading('Synchronize Currencies')
                ->modalDescription('This will fetch the latest currency rates from the API and update the database.')
                ->modalSubmitActionLabel('Synchronize')
                ->action(function () {
                    /** @var User $user */
                    $user = Auth::user();
                    SyncCurrenciesJob::dispatch($user->id);

                    Notification::make()
                        ->title('Currency Sync Started')
                        ->body('The currency synchronization process has been queued. You will receive a notification once completed.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
