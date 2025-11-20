<?php

namespace App\Filament\Resources\Registrars\Pages;

use App\Enums\RegistrarCode;
use App\Filament\Resources\Registrars\RegistrarResource;
use App\Jobs\SyncRegistrarPricesJob;
use App\Models\Registrar;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class ManageRegistrars extends ManageRecords
{
    protected static string $resource = RegistrarResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Large)
                ->createAnother(false)
                ->after(function (Registrar $record): void {
                    if ($record->api_support === RegistrarCode::NONE) {
                        return;
                    }

                    SyncRegistrarPricesJob::dispatch(
                        $record->id,
                        Auth::id()
                    );
                }),
        ];
    }
}
