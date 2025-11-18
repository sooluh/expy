<?php

namespace App\Filament\Resources\Rdaps\Pages;

use App\Filament\Resources\Rdaps\RdapResource;
use App\Jobs\SyncRdapsJob;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageRdaps extends ManageRecords
{
    protected static string $resource = RdapResource::class;

    protected function getActions(): array
    {
        return [
            Action::make('synchronize')
                ->label('Synchronize')
                ->requiresConfirmation()
                ->modalHeading('Synchronize RDAPs')
                ->modalDescription('This will fetch the latest RDAP services from IANA and update the database.')
                ->modalSubmitActionLabel('Synchronize')
                ->action(function () {
                    /** @var User $user */
                    $user = Auth::user();
                    SyncRdapsJob::dispatch($user->id);

                    Notification::make()
                        ->title('RDAP Sync Started')
                        ->body('The RDAP synchronization process has been queued. You will receive a notification once completed.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
