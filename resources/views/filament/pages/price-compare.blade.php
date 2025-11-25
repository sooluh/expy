<x-filament-panels::page>
    <div class="max-w-7xl mx-auto">
        <x-filament::section>
            <x-slot name="heading">
                Compare Registrar Prices
            </x-slot>

            <form wire:submit.prevent="submit" class="space-y-4">
                <div class="flex items-center [&>*:first-child]:grow [&>*:not(:first-child)]:ml-3">
                    {{ $this->form }}

                    <div class="flex justify-center">
                        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                            Compare
                        </x-filament::button>
                    </div>
                </div>

                @if ($error)
                    <div
                        class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-800/70 dark:bg-danger-950/60 dark:text-danger-200">
                        {{ $error }}
                    </div>
                @endif
            </form>
        </x-filament::section>
    </div>

    @if ($matched)
        {{ $this->table }}
    @endif
</x-filament-panels::page>
