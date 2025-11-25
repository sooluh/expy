<?php

namespace App\Filament\Pages;

use App\Models\RegistrarFee;
use BackedEnum;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class PriceCompare extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.price-compare';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-list-search';

    protected static string|UnitEnum|null $navigationGroup = 'Domain Management';

    protected static ?int $navigationSort = 4;

    public array $data = [
        'domain' => '',
    ];

    public ?string $matched = null;

    public ?string $error = null;

    public function submit(): void
    {
        $this->error = null;
        $this->matched = null;

        $state = $this->form->getState();
        $input = trim(strtolower($state['domain'] ?? ''));

        if ($input === '') {
            return;
        }

        try {
            $tld = $this->resolve($input);

            if ($tld === null) {
                $this->error = 'No matching TLD found.';

                return;
            }

            $this->matched = $tld;
        } catch (Exception $e) {
            Log::error('PriceCompare lookup failed', [
                'input' => $state['domain'] ?? '',
                'error' => $e->getMessage(),
            ]);

            $this->error = 'Unable to fetch prices right now. Please try again.';
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('domain')
                    ->hiddenLabel()
                    ->placeholder('example.com')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Showing result for '.($this->matched ? '.'.$this->matched : 'N/A'))
            ->query($this->getTableQuery())
            ->defaultSort('register_price')
            ->paginated(false)
            ->columns([
                TextColumn::make('registrar.name')
                    ->label('Registrar')
                    ->searchable(),

                TextColumn::make('tld')
                    ->label('TLD')
                    ->formatStateUsing(fn (string $state): string => '.'.$state),

                TextColumn::make('register_price_converted')
                    ->label('Register')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('renew_price_converted')
                    ->label('Renew')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('transfer_price_converted')
                    ->label('Transfer')
                    ->money(fn () => registrar_display_currency_code())
                    ->placeholder('—')
                    ->sortable(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        if (! $this->matched) {
            return RegistrarFee::query()->whereRaw('1 = 0');
        }

        return RegistrarFee::query()
            ->with('registrar')
            ->where('tld', $this->matched);
    }

    private function resolve(string $input): ?string
    {
        $clean = ltrim($input, '.');
        $parts = explode('.', $clean);
        $tlds = RegistrarFee::query()->distinct()->pluck('tld')->filter()->all();

        usort($tlds, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        if (count($parts) === 1) {
            return in_array($clean, $tlds, true) ? $clean : null;
        }

        foreach ($tlds as $tld) {
            $needle = '.'.$tld;

            if ($clean === $tld || str_ends_with($clean, $needle)) {
                return $tld;
            }
        }

        return null;
    }
}
