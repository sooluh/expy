<?php

namespace App\Filament\Resources\Registrars\Pages;

use App\Filament\Resources\Registrars\RegistrarResource;
use App\Jobs\SyncRegistrarPricesJob;
use App\Models\Currency;
use App\Settings\GeneralSettings;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManageRegistrarFees extends ManageRelatedRecords
{
    protected static string $resource = RegistrarResource::class;

    protected static string $relationship = 'fees';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-currency-dollar';

    public static function getNavigationLabel(): string
    {
        return 'Fees';
    }

    public function getTitle(): string
    {
        return "{$this->getOwnerRecord()->name} Fees";
    }

    protected function getCurrencyRate(): float
    {
        try {
            $settings = app(GeneralSettings::class);
            $currencyCode = $settings->currency;

            if ($currencyCode === 'USD') {
                return 1.0;
            }

            $currency = Currency::where('code', $currencyCode)->first();

            return $currency ? (float) $currency->value : 1.0;
        } catch (Exception $e) {
            return 1.0;
        }
    }

    protected function convertToUsd(?float $localPrice): ?float
    {
        if ($localPrice === null) {
            return null;
        }

        $rate = $this->getCurrencyRate();

        return $rate > 0 ? $localPrice / $rate : $localPrice;
    }

    protected function convertFromUsd(?float $usdPrice): ?float
    {
        if ($usdPrice === null) {
            return null;
        }

        return $usdPrice * $this->getCurrencyRate();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->getOwnerRecord()->hasApiSupport()) {
            $actions[] = Action::make('synchronize')
                ->label('Synchronize')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Synchronize TLD Prices')
                ->modalDescription("Fetch latest prices from {$this->getOwnerRecord()->name} API and update the database.")
                ->modalSubmitActionLabel('Synchronize')
                ->action(function () {
                    SyncRegistrarPricesJob::dispatch(
                        $this->getOwnerRecord()->id,
                        Auth::id()
                    );

                    Notification::make()
                        ->title('Price Synchronization Started')
                        ->body('The synchronization is running in the background. You will be notified when it completes.')
                        ->info()
                        ->send();
                });
        }

        $actions[] = CreateAction::make()
            ->label('New Fee')
            ->modalHeading('Add TLD Fee')
            ->modalWidth(Width::Large)
            ->schema([
                TextInput::make('tld')
                    ->label('TLD')
                    ->placeholder('com, org, xyz, etc.')
                    ->required()
                    ->unique(table: 'registrar_fees', column: 'tld', ignorable: fn ($record) => $record, modifyRuleUsing: function ($rule) {
                        return $rule->where('registrar_id', $this->getOwnerRecord()->id);
                    })
                    ->prefix('.')
                    ->regex('/^[a-z0-9]+$/i')
                    ->maxLength(50),

                TextInput::make('register_price')
                    ->label('Register Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),

                TextInput::make('renew_price')
                    ->label('Renewal Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),

                TextInput::make('transfer_price')
                    ->label('Transfer Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),

                TextInput::make('restore_price')
                    ->label('Restore Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),

                TextInput::make('privacy_price')
                    ->label('Privacy Protection Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),

                TextInput::make('misc_price')
                    ->label('Miscellaneous Price')
                    ->numeric()
                    ->prefix(fn () => app(GeneralSettings::class)->currency)
                    ->minValue(0)
                    ->step(0.01)
                    ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                    ->columnSpan(1),
            ]);

        return $actions;
    }

    public function table(Table $table): Table
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
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('register_price', $direction))
                    ->placeholder('—'),

                TextColumn::make('renew_price_converted')
                    ->label('Renew')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('renew_price', $direction))
                    ->placeholder('—'),

                TextColumn::make('transfer_price_converted')
                    ->label('Transfer')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('transfer_price', $direction))
                    ->placeholder('—'),

                TextColumn::make('restore_price_converted')
                    ->label('Restore')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('restore_price', $direction))
                    ->placeholder('—'),

                TextColumn::make('privacy_price_converted')
                    ->label('Privacy')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('privacy_price', $direction))
                    ->placeholder('—'),

                TextColumn::make('misc_price_converted')
                    ->label('Misc')
                    ->money(fn () => app(GeneralSettings::class)->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('misc_price', $direction))
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['register_price'] = $this->convertFromUsd($data['register_price']);
                        $data['renew_price'] = $this->convertFromUsd($data['renew_price']);
                        $data['transfer_price'] = $this->convertFromUsd($data['transfer_price']);
                        $data['restore_price'] = $this->convertFromUsd($data['restore_price']);
                        $data['privacy_price'] = $this->convertFromUsd($data['privacy_price']);
                        $data['misc_price'] = $this->convertFromUsd($data['misc_price']);

                        return $data;
                    })
                    ->schema([
                        TextInput::make('tld')
                            ->label('TLD')
                            ->required()
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('register_price')
                            ->label('Register Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),

                        TextInput::make('renew_price')
                            ->label('Renewal Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),

                        TextInput::make('transfer_price')
                            ->label('Transfer Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),

                        TextInput::make('restore_price')
                            ->label('Restore Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),

                        TextInput::make('privacy_price')
                            ->label('Privacy Protection Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),

                        TextInput::make('misc_price')
                            ->label('Miscellaneous Price')
                            ->numeric()
                            ->prefix(fn () => app(GeneralSettings::class)->currency)
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrateStateUsing(fn ($state) => $this->convertToUsd($state))
                            ->columnSpan(1),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
