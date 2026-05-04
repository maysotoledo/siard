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
    <style>
        .fi-wi-stats-overview [data-stat-clickable] {
            cursor: pointer;
            transition: transform 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
        }

        .fi-wi-stats-overview [data-stat-clickable]:hover {
            transform: translateY(-2px);
            background-color: rgba(0, 0, 0, 0.025);
            box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.12);
        }

        .dark .fi-wi-stats-overview [data-stat-clickable]:hover {
            background-color: rgba(255, 255, 255, 0.04);
            box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.4);
        }

        .fi-wi-stats-overview [data-stat-clickable]:focus-visible {
            outline: 2px solid rgb(var(--primary-500, 245 158 11) / 0.6);
            outline-offset: 2px;
        }
    </style>

    {{ $this->content }}

    <x-filament-actions::modals />
</x-filament-widgets::widget>
