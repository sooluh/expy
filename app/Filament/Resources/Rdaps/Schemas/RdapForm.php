<?php

namespace App\Filament\Resources\Rdaps\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RdapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('tld')
                    ->label('TLD')
                    ->required()
                    ->maxLength(255),

                Textarea::make('rdap')
                    ->label('RDAP URL')
                    ->required(),
            ]);
    }
}
