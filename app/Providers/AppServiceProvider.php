<?php

namespace App\Providers;

use App\Services\SqidsService;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sqids', function () {
            return SqidsService::getInstance();
        });
    }

    public function boot(): void
    {
        /**
         * filament timezone
         */
        FilamentTimezone::set(config('app.timezone'));

        /**
         * fix mixed content in productions
         */
        if (app()->isProduction() && ! empty(($app_url = config('app.url')))) {
            URL::forceRootUrl($app_url);
            URL::forceScheme(explode(':', $app_url)[0]);
        }

        /**
         * filament render hook
         */
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): ViewContract => view('components.public.favicon'),
        );

        /**
         * filament table behavior
         */
        Table::configureUsing(function (Table $table) {
            return $table
                ->deferFilters(false)
                ->deferColumnManager(false)
                ->deferLoading(true)
                ->searchOnBlur(true);
        });
    }
}
