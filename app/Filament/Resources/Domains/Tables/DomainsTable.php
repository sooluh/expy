<?php

namespace App\Filament\Resources\Domains\Tables;

use App\Settings\GeneralSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain_name')
                    ->label('Domain name')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrar.name')
                    ->label('Registrar')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrarFee.renew_price_converted')
                    ->label('Renew price')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->placeholder('—'),

                TextColumn::make('registration_date')
                    ->label('Registration date')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('expiration_date')
                    ->label('Expiration date')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('registrar')
                    ->label('Registrar')
                    ->relationship('registrar', 'name')
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth(Width::Large),
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
