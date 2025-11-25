<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DomainTldsTable extends TableWidget
{
    protected static ?string $heading = 'Domain TLDs';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->defaultSort('total', 'desc')
            ->columns([
                TextColumn::make('tld')
                    ->label('TLD')
                    ->badge()
                    ->color(fn () => 'primary')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
            ]);
    }

    protected function getQuery(): Builder
    {
        return Domain::query()
            ->join('registrar_fees', 'domains.registrar_fee_id', '=', 'registrar_fees.id')
            ->select([
                DB::raw("COALESCE(registrar_fees.tld, 'unknown') as tld"),
                DB::raw('COUNT(domains.id) as total'),
            ])
            ->groupBy(DB::raw("COALESCE(registrar_fees.tld, 'unknown')"));
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) ($record->tld ?? $record['tld'] ?? '');
    }
}
