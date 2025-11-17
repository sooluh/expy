<?php

namespace App\Filament\Resources\Registrars\Pages;

use App\Filament\Resources\Registrars\RegistrarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRegistrars extends ManageRecords
{
    protected static string $resource = RegistrarResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Registrar')
                ->modalWidth(Width::Large)
                ->createAnother(false),
        ];
    }
}
