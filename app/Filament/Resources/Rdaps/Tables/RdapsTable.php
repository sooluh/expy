<?php

namespace App\Filament\Resources\Rdaps\Tables;

use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RdapsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tld')
                    ->label('TLD')
                    ->badge()
                    ->searchable(),

                TextColumn::make('rdap')
                    ->label('RDAP URL')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()->modalWidth(Width::Large),
            ]);
    }
}
