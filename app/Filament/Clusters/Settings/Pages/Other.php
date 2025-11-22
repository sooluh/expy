<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Currency;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class Other extends Page implements HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.clusters.settings.pages.other';

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings-question';

    protected static ?string $navigationLabel = 'Other';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Other Settings';

    protected ?string $heading = '';

    public array $data = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->data = [
            'timezone' => $user->settings['timezone'] ?? config('app.timezone'),
            'currency' => $user->settings['currency'] ?? registrar_display_currency_code($user),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $mfaComponents = [];

        if (Filament::hasMultiFactorAuthentication()) {
            $user = Filament::auth()->user();

            $mfaComponents = collect(Filament::getMultiFactorAuthenticationProviders())
                ->sort(function (MultiFactorAuthenticationProvider $provider) use ($user): int {
                    return $provider->isEnabled($user) ? 0 : 1;
                })
                ->map(function (MultiFactorAuthenticationProvider $provider): Group {
                    return Group::make($provider->getManagementSchemaComponents())->statePath($provider->getId());
                })
                ->all();
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make($this->getTitle())
                    ->description('Other supporting settings.')
                    ->columns(1)
                    ->schema([
                        Select::make('timezone')
                            ->label('Timezone')
                            ->searchable()
                            ->required()
                            ->columnSpanFull()
                            ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($val) => [$val => $val])->toArray()),

                        Select::make('currency')
                            ->label('Currency')
                            ->searchable()
                            ->required()
                            ->options(
                                Currency::orderBy('code')
                                    ->pluck('code', 'code')
                                    ->toArray()
                            ),

                        ...$mfaComponents,
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
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
        ];

        /** @var User $user */
        $user = Auth::user();
        $user->settings($payload);

        $this->data = $payload;

        Notification::make()
            ->title('Other settings saved successfully.')
            ->success()
            ->send();
    }
}
