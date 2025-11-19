<?php

namespace App\Filament\Resources\Currencies\Tables;

use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Currency code')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('value')
                    ->label('Exchange rate')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->modalWidth(Width::Large),
            ]);
    }
}
