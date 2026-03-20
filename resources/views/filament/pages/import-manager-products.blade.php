<x-filament-panels::page>
    <form wire:submit="importData">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" size="lg" color="primary">
                Привязать товары
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>