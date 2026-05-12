@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class([
                'fi-wi-stats-overview',
            ])
    "
>
    {{ $this->content }}

    <x-filament::modal
        :id="'online-users-modal-' . $this->getId()"
        heading="Usuários online"
        width="lg"
    >
        @include('filament.resources.pixel-admin.widgets.online-users-modal', [
            'users' => $this->getOnlineUsers(),
        ])
    </x-filament::modal>
</x-filament-widgets::widget>
