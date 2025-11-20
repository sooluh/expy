<?php

namespace App\Filament\Resources\Registrars\Pages;

use App\Filament\Resources\Registrars\RegistrarResource;
use App\Filament\Resources\Registrars\Schemas\RegistrarFeeForm;
use App\Filament\Resources\Registrars\Tables\RegistrarFeesTable;
use App\Jobs\SyncRegistrarPricesJob;
use App\Models\Currency;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\Width;
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

    protected function getDisplayCurrencyCode(): string
    {
        return app(GeneralSettings::class)->currency;
    }

    protected function getRegistrarCurrencyCode(): string
    {
        return $this->getOwnerRecord()->currency->code ?? $this->getDisplayCurrencyCode();
    }

    protected function getRegistrarToDisplayRate(): float
    {
        $display = $this->getDisplayCurrencyCode();
        $registrar = $this->getRegistrarCurrencyCode();

        if ($display === $registrar) {
            return 1.0;
        }

        $baseRate = $this->getRatePerUsd($registrar);
        $targetRate = $this->getRatePerUsd($display);

        if ($baseRate <= 0) {
            return 1.0;
        }

        return $targetRate / $baseRate;
    }

    protected function getRatePerUsd(string $code): float
    {
        if ($code === 'USD') {
            return 1.0;
        }

        $currency = Currency::where('code', $code)->first();

        return $currency ? (float) $currency->value : 1.0;
    }

    public function convertDisplayToRegistrar(?float $displayPrice): ?float
    {
        if ($displayPrice === null) {
            return null;
        }

        $rate = $this->getRegistrarToDisplayRate();

        return $rate > 0 ? $displayPrice / $rate : $displayPrice;
    }

    public function convertRegistrarToDisplay(?float $registrarPrice): ?float
    {
        if ($registrarPrice === null) {
            return null;
        }

        return $registrarPrice * $this->getRegistrarToDisplayRate();
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
            ->modalWidth(Width::Large)
            ->schema(RegistrarFeeForm::configure($this));

        return $actions;
    }

    public function table(Table $table): Table
    {
        return RegistrarFeesTable::configure($table, $this);
    }
}
