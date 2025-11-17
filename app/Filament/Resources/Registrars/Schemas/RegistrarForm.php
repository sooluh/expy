<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Enums\ApiSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RegistrarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Registrar Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('url')
                    ->label('Registrar URL')
                    ->required()
                    ->url(),

                Select::make('currency_id')
                    ->label('Currency')
                    ->required()
                    ->native(false)
                    ->relationship('currency', 'code')
                    ->searchable(),

                TextInput::make('notes')
                    ->label('Notes')
                    ->nullable(),

                Select::make('api_support')
                    ->label('API Support')
                    ->required()
                    ->native(false)
                    ->options(ApiSupport::class)
                    ->default(ApiSupport::NONE),
            ]);
    }
}
