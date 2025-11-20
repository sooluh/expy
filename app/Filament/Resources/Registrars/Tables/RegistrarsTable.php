<?php

namespace App\Filament\Resources\Registrars\Tables;

use App\Enums\RegistrarCode;
use App\Filament\Resources\Registrars\RegistrarResource;
use App\Jobs\SyncRegistrarPricesJob;
use App\Models\Registrar;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\Auth;

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
                    ->label('Registrar name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                IconColumn::make('api_support')
                    ->label('API support')
                    ->state(fn ($record) => $record->api_support !== RegistrarCode::NONE)
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
                Action::make('fees')
                    ->label('Fees')
                    ->icon('tabler-currency-dollar')
                    ->color('info')
                    ->url(fn (Registrar $record): string => RegistrarResource::getUrl('fees', ['record' => $record])),
                EditAction::make()
                    ->modalWidth(Width::Large)
                    ->after(function (Registrar $record): void {
                        if ($record->api_support === RegistrarCode::NONE) {
                            return;
                        }

                        if (! $record->wasChanged('api_support')) {
                            return;
                        }

                        SyncRegistrarPricesJob::dispatch(
                            $record->id,
                            Auth::id()
                        );
                    }),
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
