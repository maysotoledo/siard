<?php

namespace App\Filament\Resources\AfastamentoSolicitacoes\Pages;

use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoSolicitacoes\AfastamentoSolicitacaoResource;
use App\Filament\Widgets\AfastamentosCalendarWidget;
use App\Models\AfastamentoSolicitacao;
use App\Services\Afastamentos\AfastamentoService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ManageAfastamentoSolicitacoes extends ManageRecords
{
    protected static string $resource = AfastamentoSolicitacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Solicitar afastamento')
                ->fillForm(fn (): array => [
                    'tipo_afastamento' => TipoAfastamento::FERIAS->value,
                    'user_id' => auth()->id(),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AfastamentosCalendarWidget::class,
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(AfastamentoService::class)->salvar($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof AfastamentoSolicitacao);

        return app(AfastamentoService::class)->salvar($data, $record);
    }

    #[On('afastamentosUpdated')]
    public function onAfastamentosUpdated(): void
    {
        $this->resetTable();
        $this->dispatch('$refresh');
    }
}
