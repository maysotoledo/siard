<?php

namespace App\Filament\Widgets;

use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoSolicitacoes\AfastamentoSolicitacaoResource;
use App\Models\AfastamentoSolicitacao;
use App\Services\Afastamentos\AfastamentoConflictService;
use App\Services\Afastamentos\AfastamentoService;
use App\Services\Afastamentos\AfastamentoSuggestionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AfastamentosAllWidget extends TableWidget
{
    protected static ?string $heading = 'Todos os afastamentos';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('dias_solicitados')->label('Dias')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('nivel_impacto')
                    ->label('Impacto')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('impacto_score')->label('Score')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_afastamento')->label('Tipo')->options(TipoAfastamento::options()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(StatusAfastamento::options()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Servidor')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Actions\Action::make('abrirCadastro')
                    ->label('Abrir afastamentos')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn () => $this->isGestor())
                    ->url(AfastamentoSolicitacaoResource::getUrl()),
            ])
            ->recordActions([
                Actions\Action::make('enviar')
                    ->label('Enviar para análise')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (AfastamentoSolicitacao $record) => in_array($record->status, [StatusAfastamento::RASCUNHO, StatusAfastamento::SOLICITADO], true))
                    ->action(fn (AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->enviarParaAnalise($record),
                        'Enviado para análise',
                    )),
                Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('gray')
                    ->schema([
                        Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->cancelar($record, $data['justificativa']),
                        'Afastamento cancelado',
                    )),
                Actions\Action::make('interromper')
                    ->label('Interromper')
                    ->color('warning')
                    ->visible(fn () => $this->isGestor())
                    ->schema([
                        Forms\Components\DatePicker::make('data_interrupcao')->label('Data da interrupção')->required()->native(false),
                        Forms\Components\Textarea::make('motivo')->label('Motivo')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->interromper($record, $data['data_interrupcao'], $data['motivo']),
                        'Afastamento interrompido',
                    )),
                Actions\Action::make('recalcular')
                    ->label('Recalcular impacto')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->recalcularImpacto($record),
                        'Impacto recalculado',
                    )),
                Actions\Action::make('analise')
                    ->label('Análise')
                    ->icon('heroicon-o-sparkles')
                    ->modalHeading('Análise inteligente')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (AfastamentoSolicitacao $record) => view('filament.pages.partials.afastamento-analise', [
                        'conflitos' => app(AfastamentoConflictService::class)->detectar($record),
                        'sugestoes' => app(AfastamentoSuggestionService::class)->sugerir($record),
                    ])),
                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir afastamento?')
                    ->modalDescription('Isso removerá a solicitação e seu histórico vinculado. Esta ação deve ser usada apenas para correção administrativa.'),
            ])
            ->defaultSort('data_inicio', 'desc');
    }

    private function query(): Builder
    {
        $query = AfastamentoSolicitacao::query()->with(['user', 'periodoAquisitivo']);
        $user = auth()->user();

        if ($user && ! $this->canViewTodos()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function executar(callable $callback, string $ok): void
    {
        try {
            $callback();
            Notification::make()->title($ok)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Ação não permitida')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            throw $exception;
        }
    }

    private function isGestor(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('chefia') || $user->hasRole('dpc'));
    }

    private function canViewTodos(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $this->isGestor() ||
            $user->can('ViewAny:AfastamentoSolicitacao') ||
            $user->can('View:DashboardAfastamentos') ||
            $user->can('view:DashboardAfastamentos')
        );
    }
}
