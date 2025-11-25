<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DomainTldsChart extends ChartWidget
{
    protected ?string $heading = 'Domain TLDs';

    protected static ?int $sort = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $rows = Domain::query()
            ->join('registrar_fees', 'domains.registrar_fee_id', '=', 'registrar_fees.id')
            ->select('registrar_fees.tld', DB::raw('COUNT(domains.id) as total'))
            ->groupBy('registrar_fees.tld')
            ->orderByDesc('total')
            ->get();

        $labels = $rows->pluck('tld')->all();
        $data = $rows->pluck('total')->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah domain',
                    'data' => $data,
                    'backgroundColor' => $this->colors($labels),
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    private function colors(array $labels): array
    {
        $palette = [
            '#0ea5e9',
            '#6366f1',
            '#22c55e',
            '#f59e0b',
            '#ef4444',
            '#14b8a6',
            '#a855f7',
            '#f97316',
            '#84cc16',
            '#06b6d4',
        ];

        return array_map(
            fn ($index) => $palette[$index % count($palette)],
            array_keys($labels)
        );
    }
}
