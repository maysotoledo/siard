@php
    $user = filament()->auth()->user();
    $brandLogoUrl = asset('images/siard-dashboard.png');
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>

        <div class="flex flex-col items-center justify-center gap-4 text-center w-full py-4">

            {{-- Avatar --}}
            <x-filament-panels::avatar.user
                size="lg"
                :user="$user"
                loading="lazy"
            />

            {{-- Nome --}}
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Bem-vindo,</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ filament()->getUserName($user) }}
                </p>
            </div>

            {{-- Imagem SIARD --}}
            @if ($brandLogoUrl)
                <img
                    src="{{ $brandLogoUrl }}"
                    alt="SIARD"
                    class="rounded-2xl object-contain"
                    style="max-width: 500px; width: 100%; max-height: 300px; object-fit: contain;"
                >
            @endif

        </div>

    </x-filament::section>
</x-filament-widgets::widget>
