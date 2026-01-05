<?php

namespace App\Filament\Resources\Currencies\Pages;

use App\Filament\Resources\Currencies\CurrencyResource;
use App\Jobs\SyncCurrenciesJob;
use App\Models\User;
use App\Settings\CurrencyapiSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageCurrencies extends ManageRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getActions(): array
    {
        $currencyapi = app(CurrencyapiSettings::class);
        $hasApiKey = filled($currencyapi->api_key);

        return [
            Action::make('pre-synchronize')
                ->label('Synchronize')
                ->color('warning')
                ->visible(! $hasApiKey)
                ->requiresConfirmation()
                ->modalHeading('Currency API key required')
                ->modalDescription('Please configure your CurrencyAPI API key first via Settings then Integration.')
                ->modalSubmitActionLabel('Open settings')
                ->action(fn () => redirect(route('filament.studio.settings.pages.integration'))),

            Action::make('synchronize')
                ->label('Synchronize')
                ->requiresConfirmation()
                ->modalHeading('Synchronize Currencies')
                ->modalDescription('This will fetch the latest currency rates from the API and update the database.')
                ->modalSubmitActionLabel('Synchronize')
                ->visible($hasApiKey)
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
