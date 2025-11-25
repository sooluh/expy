<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use App\Models\Registrar;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $currency = registrar_display_currency_code(Auth::user());
        $domains = Domain::with('registrarFee')->get();
        $totalDomains = $domains->count();

        $annualSpending = $domains
            ->map(fn (Domain $domain) => $domain->registrarFee?->renew_price_converted)
            ->filter()
            ->sum();

        $expiringSoon = Domain::whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addMonths(3))
            ->count();

        $registrarsWithDomains = Registrar::whereIn('id', Domain::whereNotNull('registrar_id')->pluck('registrar_id')->unique())->count();

        return [
            Stat::make('Total Domains', $totalDomains),
            Stat::make('Annual Renew (~'.$currency.')', number_format((float) $annualSpending, 2)),
            Stat::make('Expiring in 3 Months', $expiringSoon),
            Stat::make('Registrars with Domains', $registrarsWithDomains),
        ];
    }
}
