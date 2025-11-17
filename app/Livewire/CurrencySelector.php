<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;

class CurrencySelector extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(GeneralSettings $settings): void
    {
        $this->form->fill(['currency' => $settings->currency]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currency')
                    ->hiddenLabel()
                    ->options(Currency::query()->pluck('code', 'code'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, GeneralSettings $settings) {
                        $settings->currency = $state;
                        $settings->save();
                    }),
            ])
            ->statePath('data');
    }

    public function render()
    {
        return view('filament.components.currency-selector');
    }
}
