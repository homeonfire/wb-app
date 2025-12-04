import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    // 3. ЖЕЛЕЗОБЕТОННО: Запрещаем удалять эти классы
    safelist: [
        'text-green-600',
        'text-green-500',
        'text-red-600',
        'text-red-500',
        'dark:text-green-400',
        'dark:text-green-500',
        'dark:text-red-400',
        'dark:text-red-500',
        'bg-green-50',
        'bg-red-50',
    ],
}
