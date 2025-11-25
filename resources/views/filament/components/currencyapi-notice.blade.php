<div x-data x-init="$nextTick(() => $dispatch('open-modal', { id: 'currency-api-modal' }))">
    <x-filament::modal id="currency-api-modal" heading="API Key Required" width="lg" :close-button="false"
        :close-by-escaping="false" :close-by-clicking-away="false">
        <p class="text-base leading-relaxed">
            You need to fill in the <strong>Currency API Key</strong> before continuing to use the application.
        </p>

        <x-slot name="footer">
            <x-filament::button color="primary" tag="a" href="{{ route('filament.studio.settings.pages.integration') }}">
                Fill in API Key Now
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
