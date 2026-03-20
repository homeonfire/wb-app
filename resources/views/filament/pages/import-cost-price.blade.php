<x-filament-panels::page>
    <form wire:submit="importData">
        {{-- Рендерим саму форму из PHP класса --}}
        {{ $this->form }}

        {{-- Кнопка отправки --}}
        <div class="mt-6">
            <x-filament::button type="submit" size="lg" color="primary">
                Загрузить и обновить себестоимость
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>