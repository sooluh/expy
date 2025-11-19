<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Settings\CurrencyapiSettings;
use App\Settings\ScrapingantSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Integration extends Page implements HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.clusters.settings.pages.integration';

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-layers-linked';

    protected static ?string $navigationLabel = 'Integration';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Integration Settings';

    protected ?string $heading = '';

    public array $data = [];

    public function mount(): void
    {
        $currencyapi = app(CurrencyapiSettings::class);
        $scrapingant = app(ScrapingantSettings::class);

        $this->data = [
            'currencyapi' => $currencyapi->api_key,
            'scrapingant' => $scrapingant->api_key,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make($this->getTitle())
                    ->description('Update your integration settings below.')
                    ->columns(1)
                    ->schema([
                        TextInput::make('currencyapi')
                            ->label('Currency API key')
                            ->required()
                            ->password()
                            ->revealable()
                            ->belowContent([
                                'Get your API key from',
                                Action::make('currencyapi-link')
                                    ->label('Currencyapi.com')
                                    ->url('https://currencyapi.com/?ref=sooluh/expy')
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ]),

                        TextInput::make('scrapingant')
                            ->label('Scrapingant API key')
                            ->password()
                            ->revealable()
                            ->belowContent([
                                'Get your API key from',
                                Action::make('scrapingant-link')
                                    ->label('ScrapingAnt')
                                    ->url('https://scrapingant.com/?ref=sooluh/expy')
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ]),
                    ]),
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $payload = [
            'currencyapi' => $data['currencyapi'],
            'scrapingant' => $data['scrapingant'],
        ];

        $currencyapi = app(CurrencyapiSettings::class);
        $currencyapi->api_key = $payload['currencyapi'];
        $currencyapi->save();

        $scrapingant = app(ScrapingantSettings::class);
        $scrapingant->api_key = $payload['scrapingant'];
        $scrapingant->save();

        $this->data = $payload;

        Notification::make()
            ->body('Integration settings successfully updated.')
            ->success()
            ->send();
    }
}
