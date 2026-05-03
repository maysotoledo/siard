<?php

namespace App\Filament\Resources\AfastamentoSolicitacoes\Pages;

use App\Filament\Resources\AfastamentoSolicitacoes\AfastamentoSolicitacaoResource;
use App\Filament\Widgets\AfastamentosCalendarWidget;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\On;

class ManageAfastamentoSolicitacoes extends ManageRecords
{
    protected static string $resource = AfastamentoSolicitacaoResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AfastamentosCalendarWidget::class,
        ];
    }

    #[On('afastamentosUpdated')]
    public function onAfastamentosUpdated(): void
    {
        $this->resetTable();
        $this->dispatch('$refresh');
    }
}
