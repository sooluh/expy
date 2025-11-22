<?php

namespace App\Http\Livewire;

use App\Models\Currency;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CurrencySelector extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(GeneralSettings $settings): void
    {
        $user = Auth::user();
        $default = $settings->currency;
        $current = $user?->settings['currency'] ?? $default;

        $this->form->fill(['currency' => $current]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currency')
                    ->hiddenLabel()
                    ->options(Currency::query()->pluck('code', 'code'))
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, GeneralSettings $settings) {
                        /** @var User|null $user */
                        $user = Auth::user();

                        if ($user) {
                            $user->settings(['currency' => $state]);
                        } else {
                            $settings->currency = $state;
                            $settings->save();
                        }

                        request()->header('Referer')
                            ? redirect(request()->header('Referer'))
                            : redirect()->refresh();
                    }),
            ])
            ->statePath('data');
    }

    public function render()
    {
        return view('filament.components.currency-selector');
    }
}
