<?php

namespace App\Filament\Resources\Registrars\Tables;

use App\Filament\Resources\Registrars\Pages\ManageRegistrarFees;
use App\Filament\Resources\Registrars\Schemas\RegistrarFeeForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrarFeesTable
{
    public static function configure(Table $table, ManageRegistrarFees $page): Table
    {
        return $table
            ->columns([
                TextColumn::make('tld')
                    ->label('TLD')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('register_price_converted')
                    ->label('Register')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('register_price', $direction);
                    }),

                TextColumn::make('renew_price_converted')
                    ->label('Renew')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('renew_price', $direction);
                    }),

                TextColumn::make('transfer_price_converted')
                    ->label('Transfer')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('transfer_price', $direction);
                    }),

                TextColumn::make('restore_price_converted')
                    ->label('Restore')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('restore_price', $direction);
                    }),

                TextColumn::make('privacy_price_converted')
                    ->label('Privacy')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('privacy_price', $direction);
                    }),

                TextColumn::make('misc_price_converted')
                    ->label('Misc')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('misc_price', $direction);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Large)
                    ->schema(RegistrarFeeForm::configure($page, isEditing: true)),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
