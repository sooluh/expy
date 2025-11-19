<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageDomains extends ManageRecords
{
    protected static string $resource = DomainResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Large)
                ->createAnother(false),
        ];
    }
}
