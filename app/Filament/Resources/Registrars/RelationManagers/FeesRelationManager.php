<?php

namespace App\Filament\Resources\Registrars\RelationManagers;

use App\Filament\Resources\Registrars\RegistrarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class FeesRelationManager extends RelationManager
{
    protected static string $relationship = 'fees';

    protected static ?string $relatedResource = RegistrarResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
