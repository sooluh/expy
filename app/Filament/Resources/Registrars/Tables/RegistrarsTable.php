<?php

namespace App\Filament\Resources\Registrars\Tables;

use App\Enums\ApiSupport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RegistrarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('notes')
                    ->label('')
                    ->icon('tabler-message')
                    ->tooltip(fn ($state) => $state ?: null),

                TextColumn::make('name')
                    ->label('Registrar Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                IconColumn::make('api_support')
                    ->label('API Support')
                    ->state(fn ($record) => $record->api_support !== ApiSupport::NONE)
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make()->native(false),
            ])
            ->recordActions([
                EditAction::make()->modalWidth(Width::Large),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
