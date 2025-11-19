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
use Illuminate\Support\Facades\Hash;

class Password extends Page implements HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.clusters.settings.pages.password';

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-lock';

    protected static ?string $navigationLabel = 'Password';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Change Password';

    protected ?string $heading = '';

    public array $data = [];

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->statePath('data')
            ->components([
                Section::make($this->getTitle())
                    ->description('Update your account password.')
                    ->columns(1)
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current password')
                            ->password()
                            ->revealable()
                            ->required(fn () => $user?->password !== null)
                            ->hidden(fn () => $user?->password === null)
                            ->autocomplete(false),

                        TextInput::make('new_password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->rule('confirmed')
                            ->autocomplete(false),

                        TextInput::make('new_password_confirmation')
                            ->label('Repeat new password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete(false),
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
        /** @var User $user */
        $user = Auth::user();
        $state = $this->form->getState();

        if ($user->password !== null && ! Hash::check($state['current_password'] ?? '', $user->password)) {
            Notification::make()
                ->title('Current password is incorrect.')
                ->danger()
                ->send();

            return;
        }

        $user->password = $state['new_password'];
        $user->save();

        $this->data['current_password'] = null;
        $this->data['new_password'] = null;
        $this->data['new_password_confirmation'] = null;

        Notification::make()
            ->title('Password successfully updated.')
            ->success()
            ->send();
    }
}
