<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Settings\Pages\Profile;
use App\Filament\Pages\Login;
use App\Filament\Pages\Register;
use App\Settings\CurrencyapiSettings;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Enums\AuthLayout;
use Caresome\FilamentAuthDesigner\Enums\ThemePosition;
use Cmsmaxinc\FilamentErrorPages\FilamentErrorPagesPlugin;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Muazzam\SlickScrollbar\SlickScrollbarPlugin;
use Qopiku\FilamentSqids\Middleware\FilamentSqidsMiddleware;

class StudioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('studio')
            ->path('studio')
            ->passwordReset()
            ->emailVerification()
            ->sidebarWidth('15rem')
            ->colors(['primary' => Color::Emerald])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->authMiddleware([Authenticate::class])
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                FilamentSqidsMiddleware::class,
            ])
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(fn (): string => Auth::user()?->name ?: 'Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => Profile::getUrl())
                    ->sort(-100),
            ])
            ->plugins([
                SlickScrollbarPlugin::make()->color(Color::Emerald),
                FilamentErrorPagesPlugin::make(),
                AuthDesignerPlugin::make()
                    ->login(AuthLayout::None)
                    ->registration(AuthLayout::None)
                    ->passwordReset(AuthLayout::None)
                    ->themeToggle(ThemePosition::BottomRight),
            ])
            ->assets([
                Css::make('custom', Vite::asset('resources/scss/studio.scss')),
                Js::make('custom', Vite::asset('resources/js/studio.js'))->module(),
            ])
            ->bootUsing(function (Panel $panel) {
                /** @var User $user */
                $user = Auth::user();
                $timezone = config('app.timezone');

                if ($user && is_array($user->settings ?? null)) {
                    $candidate = $user->settings['timezone'] ?? null;

                    if (is_string($candidate)) {
                        $validTimezones = DateTimeZone::listIdentifiers();

                        if (in_array($candidate, $validTimezones, true)) {
                            $timezone = $candidate;
                        }
                    }
                }

                FilamentTimezone::set($timezone);
            })
            ->renderHook(PanelsRenderHook::BODY_END, function (): string {
                /** @var User $user */
                $user = Auth::user();

                if (! $user || request()->routeIs('filament.studio.settings.pages.integration')) {
                    return '';
                }

                $currencyapi = app(CurrencyapiSettings::class);
                $hasKey = filled($currencyapi->api_key);

                if (! $hasKey) {
                    return view('filament.components.currencyapi-notice')->render();
                }

                return '';
            })
            ->login(Login::class)
            ->registration(Register::class)
            ->maxContentWidth('full')
            ->breadcrumbs(false)
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->spa(hasPrefetching: true)
            ->viteTheme('resources/css/filament/studio/theme.css')
            ->defaultThemeMode(ThemeMode::System);
    }
}
