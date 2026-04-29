@php
    $user = filament()->auth()->user();
    $profileUrl = filament()->getProfileUrl();
    $googleCalendar = app(\App\Services\GoogleCalendarService::class);
    $brandLogoUrl = \App\Support\BrandingAsset::versionedUrl();
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <div class="flex flex-col items-center text-center">
            @if ($brandLogoUrl)
                <div class="mb-5 flex justify-center">
                    <img
                        src="{{ $brandLogoUrl }}"
                        alt="SACAT"
                        class="h-auto max-h-44 w-auto rounded-2xl object-contain shadow-sm"
                    >
                </div>
            @endif

            <x-filament-panels::avatar.user
                size="lg"
                :user="$user"
                loading="lazy"
            />

            <div class="fi-account-widget-main mt-4">
                <h2 class="fi-account-widget-heading">
                    {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
                </h2>

                <p class="fi-account-widget-user-name">
                    {{ filament()->getUserName($user) }}
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                <img
            @if($user?->hasRole('epc') || $user?->hasRole('cartorio_central'))
                @if($googleCalendar->canSync($user))
                    <form method="post" action="{{ route('google-calendar.disconnect') }}">
                        @csrf

                        <x-filament::button
                            color="success"
                            icon="heroicon-o-calendar-days"
                            labeled-from="sm"
                            tag="button"
                            type="submit"
                        >
                            Google Agenda conectado
                        </x-filament::button>
                    </form>
                @else
                    <x-filament::button
                        color="info"
                        icon="heroicon-o-calendar-days"
                        labeled-from="sm"
                        tag="a"
                        :href="route('google-calendar.connect')"
                        :disabled="! $googleCalendar->isConfigured()"
                        :tooltip="$googleCalendar->isConfigured() ? null : 'Configure as credenciais do Google Calendar no .env'"
                    >
                        Conectar Google Agenda
                    </x-filament::button>
                @endif

                <x-filament::button
                    color="warning"
                    icon="heroicon-o-clock"
                    labeled-from="sm"
                    tag="a"
                    :href="\App\Filament\Pages\DefinirHorarioAtendimento::getUrl()"
                >
                    Definir horario de atendimento
                </x-filament::button>
            @endif

            @if($profileUrl)
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
    </x-filament::section>
</x-filament-widgets::widget>
