<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6 integration-settings-page">
        {{ $this->form }}

        <div class="fi-form-actions flex items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
