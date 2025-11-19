<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
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
use Illuminate\Support\Facades\Auth;

class Profile extends Page implements HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.clusters.settings.pages.profile';

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-user';

    protected static ?string $navigationLabel = 'Profile';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Profile Settings';

    protected ?string $heading = '';

    public array $data = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->data = [
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make($this->getTitle())
                    ->description('General settings for your profile.')
                    ->columns(1)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email address')
                            ->required()
                            ->unique('users', 'email', ignorable: fn () => Auth::user()),

                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique('users', 'username', ignorable: fn () => Auth::user()),

                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(255),
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
            'username' => $data['username'],
            'email' => Auth::user()->email,
            'name' => $data['name'],
        ];

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill($payload)->save();

        $this->data = $payload;

        Notification::make()
            ->title('Profile successfully updated.')
            ->success()
            ->send();
    }
}
