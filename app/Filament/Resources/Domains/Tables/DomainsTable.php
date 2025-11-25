<?php

namespace App\Filament\Resources\Domains\Tables;

use App\Enums\DomainSyncStatus;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expiration_date')
            ->columns([
                IconColumn::make('nameservers_status')
                    ->label('')
                    ->state(fn ($record) => self::usesCloudflare($record->nameservers))
                    ->icon(fn (bool $state) => $state ? 'tabler-brand-cloudflare' : 'tabler-world')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray')
                    ->tooltip(fn (bool $state) => $state ? 'Cloudflare DNS' : 'Non-Cloudflare DNS')
                    ->alignCenter()
                    ->grow(false),

                IconColumn::make('sync_status')
                    ->label('')
                    ->icon(fn ($record) => $record->sync_status?->getIcon())
                    ->tooltip(fn ($record) => $record->sync_status?->getLabel())
                    ->visible(fn ($record) => ($record?->sync_status ?? null) !== DomainSyncStatus::COMPLETED)
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('domain_name')
                    ->label('Domain name')
                    ->color(fn ($record) => self::domainBadgeColor($record->expiration_date))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrar.name')
                    ->label('Registrar')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrarFee.renew_price_converted')
                    ->label('Renew price')
                    ->money(fn () => registrar_display_currency_code())
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

    protected static function usesCloudflare(?array $nameservers): bool
    {
        return collect($nameservers ?? [])
            ->filter()
            ->contains(fn (string $ns) => str_ends_with(strtolower($ns), '.ns.cloudflare.com'));
    }

    protected static function domainBadgeColor(?Carbon $expiration): string
    {
        if (! $expiration || $expiration->isPast()) {
            return 'gray';
        }

        if ($expiration->isBefore(now()->addMonth())) {
            return 'danger';
        }

        if ($expiration->isBefore(now()->addMonths(3))) {
            return 'warning';
        }

        return 'success';
    }
}
