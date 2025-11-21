<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Enums\RegistrarCode;
use App\Filament\Resources\Domains\DomainResource;
use App\Jobs\SyncRegistrarDomainsJob;
use App\Models\Registrar;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class ManageDomains extends ManageRecords
{
    protected static string $resource = DomainResource::class;

    protected function getActions(): array
    {
        $registrarOptions = Registrar::orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            CreateAction::make()
                ->modalWidth(Width::Large)
                ->createAnother(false),

            Action::make('syncDomains')
                ->label('Sync Registrar Domains')
                ->modalWidth(Width::Large)
                ->schema([
                    Select::make('registrar')
                        ->label('Registrar')
                        ->options(['all' => 'All registrars'] + $registrarOptions)
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $selected = $data['registrar'] ?? null;
                    $userId = Auth::id();

                    $registrars = $selected === 'all'
                        ? Registrar::where('api_support', '!=', RegistrarCode::NONE)->get()
                        : Registrar::where('id', $selected)->get();

                    foreach ($registrars as $registrar) {
                        SyncRegistrarDomainsJob::dispatch($registrar->id, $userId);
                    }

                    Notification::make()
                        ->title('Domain sync jobs dispatched')
                        ->success()
                        ->send();
                }),
        ];
    }
}
