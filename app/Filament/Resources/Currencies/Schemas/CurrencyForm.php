<?php

namespace App\Filament\Resources\Currencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('code')
                    ->label('Currency code')
                    ->required()
                    ->unique(table: 'currencies', ignorable: fn ($record) => $record),

                TextInput::make('value')
                    ->label('Exchange rate (1 USD)')
                    ->required()
                    ->numeric(),
            ]);
    }
}
