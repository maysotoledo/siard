@php
    $user = filament()->auth()->user();
    $profileUrl = filament()->getProfileUrl();
    $brandLogoUrl = asset('images/siard-dashboard.png');
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <div class="flex flex-col gap-5">
            <div class="flex items-start justify-center gap-4 sm:justify-start sm:gap-6">
                {{-- Imagem SIARD --}}
                @if ($brandLogoUrl)
                    <div class="flex shrink-0 justify-center" style="width: 500px;">
                        <img
                            src="{{ $brandLogoUrl }}"
                            alt="SIARD"
                            class="rounded-2xl object-contain shadow-sm"
                            style="display: block; width: 500px; max-width: 500px; height: 350px; max-height: 350px; object-fit: contain;"
                        >
                    </div>
                @endif

                {{-- Boas-vindas --}}
                <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                    <x-filament-panels::avatar.user
                        size="lg"
                        :user="$user"
                        loading="lazy"
                    />

                    <div class="flex min-w-0 flex-col items-start gap-3">
                        <div class="fi-account-widget-main min-w-0 text-left">
                            <h2 class="fi-account-widget-heading">
                                Bem-vindo,
                            </h2>
                            <p class="fi-account-widget-user-name break-words text-lg font-semibold">
                                {{ filament()->getUserName($user) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($profileUrl)
                                <x-filament::button
                                    color="primary"
                                    icon="heroicon-o-key"
                                    labeled-from="sm"
                                    tag="a"
                                    :href="$profileUrl"
                                >
                                    Alterar senha
                                </x-filament::button>
                            @endif

                            <form
                                action="{{ filament()->getLogoutUrl() }}"
                                method="post"
                                class="fi-account-widget-logout-form"
                            >
                                @csrf
                                <x-filament::button
                                    color="gray"
                                    icon="heroicon-o-arrow-left-end-on-rectangle"
                                    labeled-from="sm"
                                    tag="button"
                                    type="submit"
                                >
                                    {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
                                </x-filament::button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
