<?php

namespace App\Filament\Resources\Domains\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('domain_name')
                    ->label('Domain name')
                    ->required()
                    ->maxLength(255),

                Select::make('registrar_id')
                    ->label('Registrar')
                    ->required()
                    ->relationship('registrar', 'name')
                    ->native(false),
            ]);
    }
}
