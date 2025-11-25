<div class="flex items-center justify-center min-h-[80vh]">
    <div class="text-center">
        <h1 class="text-9xl font-black text-center mb-4 relative">
            <span class="relative z-10">{{ $this->getCode() }}</span>
        </h1>

        <p class="text-xl font-semibold mb-2">
            {{ $this->getTitle() }}
        </p>

        <p class="text-xs mb-6">
            {{ $this->getDescription() }}
        </p>

        <div class="flex justify-center gap-4">
            @if (url()->previous() != url()->current())
                <x-filament::button tag="a" color="gray" :href="url()->previous()">
                    {{ __('filament-error-pages::error-pages.previous') }}
                </x-filament::button>
            @endif

            <x-filament::button tag="a" color="primary" :href="\Filament\Facades\Filament::getCurrentPanel()->getUrl()">
                {{ __('filament-error-pages::error-pages.home') }}
            </x-filament::button>
        </div>
    </div>
</div>
