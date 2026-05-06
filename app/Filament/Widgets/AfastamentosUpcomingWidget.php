<?php

namespace App\Filament\Widgets;

use App\Enums\FuncaoOperacional;
use App\Enums\StatusAfastamento;
use App\Models\AfastamentoSolicitacao;
use App\Services\Afastamentos\AfastamentoOperacionalService;
use App\Services\Plantao\PlantaoSubstituicaoService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;

class AfastamentosUpcomingWidget extends TableWidget
{
    protected static ?string $heading = 'Próximos afastamentos e risco operacional';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AfastamentoSolicitacao::query()
                    ->with(['user', 'coberturasPlantao.servidorCobertura'])
                    ->whereIn('status', [StatusAfastamento::APROVADO->value, StatusAfastamento::EM_ANALISE->value])
                    ->whereDate('data_inicio', '>=', now()->toDateString())
                    ->orderBy('data_inicio')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor'),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('nivel_impacto')->label('Impacto')->formatStateUsing(fn ($state) => $state?->label() ?? '-')->badge()->color(fn ($state) => $state?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('cobertura_plantao')
                    ->label('Cobertura')
                    ->state(function (AfastamentoSolicitacao $record): string {
                        if (! $this->isPlantao($record)) {
                            return '-';
                        }

                        $cobertura = $record->coberturasPlantao
                            ->whereIn('status', ['aprovada', 'sugerida'])
                            ->sortByDesc(fn ($c) => $c->status === 'aprovada' ? 1 : 0)
                            ->first();

                        return $cobertura?->servidorCobertura?->name ?? 'Não definida';
                    })
                    ->badge()
                    ->color(function (AfastamentoSolicitacao $record): string {
                        if (! $this->isPlantao($record)) {
                            return 'gray';
                        }

                        $cobertura = $record->coberturasPlantao
                            ->whereIn('status', ['aprovada', 'sugerida'])
                            ->first();

                        if (! $cobertura) {
                            return 'danger';
                        }

                        return $cobertura->status === 'aprovada' ? 'success' : 'warning';
                    }),
            ])
            ->recordActions([
                Actions\Action::make('definir_cobertura')
                    ->label('Cobertura')
                    ->icon('heroicon-o-user-plus')
                    ->visible(
                        fn (AfastamentoSolicitacao $record): bool =>
                            $this->canGerenciarCobertura() && $this->isPlantao($record)
                    )
                    ->schema([
                        Forms\Components\Select::make('servidor_cobertura_id')
                            ->label('Servidor para cobertura')
                            ->required()
                            ->options(fn (AfastamentoSolicitacao $record): array => app(AfastamentoOperacionalService::class)->servidoresDisponiveisParaCobertura($record))
                            ->searchable()
                            ->preload()
                            ->helperText('Servidores de expediente disponíveis para cobrir o plantão.'),
                        Forms\Components\Textarea::make('observacao')->label('Observação'),
                    ])
                    ->fillForm(fn (AfastamentoSolicitacao $record): array => [
                        'servidor_cobertura_id' => $record->coberturasPlantao()
                            ->whereIn('status', ['sugerida', 'aprovada'])
                            ->latest()
                            ->value('servidor_cobertura_id')
                            ?: app(AfastamentoOperacionalService::class)->sugerirServidorCobertura($record)?->id,
                    ])
                    ->action(function (array $data, AfastamentoSolicitacao $record): void {
                        try {
                            // Afastamento já aprovado → cobertura entra direto como aprovada
                            // para que o reconciliar() propague imediatamente ao calendário.
                            $statusCobertura = $record->status === StatusAfastamento::APROVADO
                                ? 'aprovada'
                                : 'sugerida';

                            app(AfastamentoOperacionalService::class)->definirCobertura(
                                $record,
                                (int) $data['servidor_cobertura_id'],
                                $statusCobertura,
                                $data['observacao'] ?? null,
                            );

                            if ($record->refresh()->status === StatusAfastamento::APROVADO) {
                                app(PlantaoSubstituicaoService::class)->reconciliar($record);
                                $this->dispatch('plantaoUpdated');
                            }

                            Notification::make()->title('Cobertura definida')->success()->send();
                            $this->dispatch('$refresh');
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Não foi possível definir a cobertura')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            throw $exception;
                        }
                    }),
            ]);
    }

    private function isPlantao(AfastamentoSolicitacao $record): bool
    {
        return in_array(
            $record->user?->funcao_operacional,
            [FuncaoOperacional::IPC_PLANTAO, FuncaoOperacional::EPC_PLANTAO],
            true,
        );
    }

    private function canGerenciarCobertura(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasAnyRole(['admin', 'super_admin']) ||
            $user->can('GerenciarCobertura:AfastamentoSolicitacao') ||
            $user->can('Update:AfastamentoSolicitacao')
        );
    }
}
