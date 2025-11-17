<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Login;
use Cmsmaxinc\FilamentErrorPages\FilamentErrorPagesPlugin;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Qopiku\FilamentSqids\Middleware\FilamentSqidsMiddleware;

class StudioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('studio')
            ->path('studio')
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification()
            ->sidebarWidth('15rem')
            ->colors(['primary' => Color::Emerald])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->authMiddleware([Authenticate::class])
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
            ->plugins([
                FilamentErrorPagesPlugin::make(),
            ])
            ->assets([
                Css::make('custom', Vite::asset('resources/scss/studio.scss')),
                Js::make('custom', Vite::asset('resources/js/studio.js'))->module(),
            ])
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->breadcrumbs(false)
            ->collapsibleNavigationGroups(false)
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s')
            ->spa(hasPrefetching: true)
            ->viteTheme('resources/css/filament/studio/theme.css')
            ->defaultThemeMode(ThemeMode::System);
    }
}
