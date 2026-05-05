<?php

namespace App\Filament\Resources\AfastamentoSolicitacoes;

use App\Enums\FuncaoOperacional;
use App\Enums\StatusAfastamento;
use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoConflictService;
use App\Services\Afastamentos\AfastamentoOperacionalService;
use App\Services\Afastamentos\AfastamentoPrioridadeService;
use App\Services\Afastamentos\AfastamentoService;
use App\Services\Afastamentos\AfastamentoSuggestionService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class AfastamentoSolicitacaoResource extends Resource
{
    protected static ?string $model = AfastamentoSolicitacao::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Afastamentos';

    protected static ?string $modelLabel = 'Afastamento';

    protected static ?string $pluralModelLabel = 'Afastamentos';

    protected static ?string $slug = 'afastamentos';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'periodoAquisitivo']);
        $user = auth()->user();

        if ($user && ! ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('chefia') || $user->hasRole('dpc'))) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('user_id')
                ->label('Servidor')
                ->required()
                ->default(fn () => auth()->id())
                ->disabled(fn () => ! self::isGestor())
                ->dehydrated(true)
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('periodo_aquisitivo_id', null)),

            Forms\Components\Select::make('tipo_afastamento')
                ->label('Tipo')
                ->required()
                ->placeholder('')
                ->selectablePlaceholder(false)
                ->options(TipoAfastamento::options())
                ->default(TipoAfastamento::FERIAS->value)
                ->live()
                ->afterStateHydrated(function (?string $state, Set $set): void {
                    if (blank($state)) {
                        $set('tipo_afastamento', TipoAfastamento::FERIAS->value);
                    }
                })
                ->afterStateUpdated(fn (Set $set) => $set('periodo_aquisitivo_id', null)),

            Forms\Components\Select::make('periodo_aquisitivo_id')
                ->label('Período aquisitivo')
                ->hidden(fn (Get $get): bool => self::isAtestado((string) $get('tipo_afastamento')))
                ->hiddenJs("\$get('tipo_afastamento') === 'atestado'")
                ->options(function (Get $get): array {
                    $userId = (int) $get('user_id');
                    $tipo = (string) $get('tipo_afastamento');

                    if (! $userId || $tipo === '' || self::isAtestado($tipo)) {
                        return [];
                    }

                    return AfastamentoPeriodoAquisitivo::query()
                        ->where('user_id', $userId)
                        ->where('tipo_afastamento', $tipo)
                        ->where('dias_disponiveis', '>', 0)
                        ->whereDate('data_aquisicao', '<=', now()->toDateString())
                        ->whereIn('status', [
                            StatusPeriodoAquisitivo::ADQUIRIDO->value,
                            StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO->value,
                            StatusPeriodoAquisitivo::APROVADO->value,
                        ])
                        ->orderBy('data_aquisicao')
                        ->get()
                        ->mapWithKeys(fn (AfastamentoPeriodoAquisitivo $periodo): array => [
                            $periodo->id => $periodo->data_inicio?->format('d/m/Y') . ' - ' . $periodo->data_fim?->format('d/m/Y') . ' | saldo ' . $periodo->dias_disponiveis,
                        ])
                        ->all();
                })
                ->searchable()
                ->preload()
                ->helperText(fn (Get $get): string => self::deveMostrarAvisoSemPeriodoAquisitivo($get)
                    ? 'O servidor não possui período aquisitivo adquirido com saldo disponível para este tipo de afastamento.'
                    : 'Selecione um período com saldo disponível.'),

            Forms\Components\DatePicker::make('data_inicio')
                ->label('Início')
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => self::atualizarDias($set, $state, $get('data_fim'))),

            Forms\Components\DatePicker::make('data_fim')
                ->label('Fim')
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => self::atualizarDias($set, $get('data_inicio'), $state)),

            Forms\Components\TextInput::make('dias_solicitados')
                ->label('Dias solicitados')
                ->numeric()
                ->readOnly()
                ->default(0),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(StatusAfastamento::options())
                ->default(fn (Get $get): string => TipoAfastamento::tryFrom((string) $get('tipo_afastamento')) === TipoAfastamento::ATESTADO
                    ? StatusAfastamento::APROVADO->value
                    : StatusAfastamento::RASCUNHO->value)
                ->disabled(fn () => ! self::isGestor())
                ->dehydrated(true),

            Forms\Components\Textarea::make('justificativa_servidor')
                ->label('Justificativa do servidor')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('justificativa_chefia')
                ->label('Justificativa da chefia')
                ->columnSpanFull()
                ->visible(fn () => self::isGestor()),

            Forms\Components\Textarea::make('observacao')
                ->label('Observação')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.funcao_operacional')->label('Função')->formatStateUsing(fn ($state) => $state?->label() ?? '-')->badge(),
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
                Tables\Columns\TextColumn::make('prioridade_score')->label('Prioridade')->sortable(),
                Tables\Columns\TextColumn::make('prioridade_nivel')->label('Nível prioridade')->badge(),
                Tables\Columns\TextColumn::make('prioridade_posicao')->label('Ranking')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_afastamento')->label('Tipo')->options(TipoAfastamento::options()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(StatusAfastamento::options()),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('enviar')
                    ->label('Enviar para análise')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (AfastamentoSolicitacao $record) => in_array($record->status, [StatusAfastamento::RASCUNHO, StatusAfastamento::SOLICITADO], true))
                    ->action(function (AfastamentoSolicitacao $record): void {
                        app(AfastamentoService::class)->enviarParaAnalise($record);
                        Notification::make()->title('Enviado para análise')->success()->send();
                    }),
                Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('gray')
                    ->schema([
                        Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => self::executar(fn () => app(AfastamentoService::class)->cancelar($record, $data['justificativa']), 'Afastamento cancelado')),
                Actions\Action::make('interromper')
                    ->label('Interromper')
                    ->color('warning')
                    ->visible(fn () => self::isGestor())
                    ->schema([
                        Forms\Components\DatePicker::make('data_interrupcao')->label('Data da interrupção')->required()->native(false),
                        Forms\Components\Textarea::make('motivo')->label('Motivo')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => self::executar(fn () => app(AfastamentoService::class)->interromper($record, $data['data_interrupcao'], $data['motivo']), 'Afastamento interrompido')),
                Actions\Action::make('recalcular')
                    ->label('Recalcular impacto')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (AfastamentoSolicitacao $record) => self::executar(fn () => app(AfastamentoService::class)->recalcularImpacto($record), 'Impacto recalculado')),
                Actions\Action::make('definir_cobertura')
                    ->label('Alterar cobertura de plantão')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (AfastamentoSolicitacao $record): bool => self::canGerenciarCobertura() && in_array(
                        $record->user?->funcao_operacional,
                        [FuncaoOperacional::IPC_PLANTAO, FuncaoOperacional::EPC_PLANTAO],
                        true,
                    ))
                    ->schema([
                        Forms\Components\Select::make('servidor_cobertura_id')
                            ->label('Servidor para cobertura')
                            ->required()
                            ->options(fn (AfastamentoSolicitacao $record): array => app(AfastamentoOperacionalService::class)->servidoresDisponiveisParaCobertura($record))
                            ->searchable()
                            ->preload()
                            ->helperText('A análise operacional sugere um servidor de expediente disponível. Use este campo para trocar ou confirmar a cobertura.'),
                        Forms\Components\Textarea::make('observacao')->label('Observação'),
                    ])
                    ->fillForm(fn (AfastamentoSolicitacao $record): array => [
                        'servidor_cobertura_id' => $record->coberturasPlantao()
                            ->whereIn('status', ['sugerida', 'aprovada'])
                            ->latest()
                            ->value('servidor_cobertura_id')
                            ?: app(AfastamentoOperacionalService::class)->sugerirServidorCobertura($record)?->id,
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => self::executar(function () use ($data, $record): void {
                        app(AfastamentoOperacionalService::class)->definirCobertura(
                            $record,
                            (int) $data['servidor_cobertura_id'],
                            'sugerida',
                            $data['observacao'] ?? null,
                        );

                        if ($record->refresh()->status === StatusAfastamento::APROVADO) {
                            app(\App\Services\Plantao\PlantaoSubstituicaoService::class)->reconciliar($record);
                        }
                    }, 'Cobertura sugerida')),
                Actions\Action::make('aprovar_cobertura')
                    ->label('Aprovar cobertura')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (AfastamentoSolicitacao $record): bool => self::canGerenciarCobertura() && $record->coberturasPlantao()->where('status', 'sugerida')->exists())
                    ->action(fn (AfastamentoSolicitacao $record) => self::executar(function () use ($record): void {
                        $record->coberturasPlantao()
                            ->where('status', 'sugerida')
                            ->latest()
                            ->first()
                            ?->forceFill([
                                'status' => 'aprovada',
                                'aprovado_por' => auth()->id(),
                                'aprovado_em' => now(),
                            ])
                            ->save();

                        if ($record->refresh()->status === StatusAfastamento::APROVADO) {
                            app(\App\Services\Plantao\PlantaoSubstituicaoService::class)->reconciliar($record);
                        }
                    }, 'Cobertura aprovada')),
                Actions\Action::make('cancelar_cobertura')
                    ->label('Cancelar cobertura')
                    ->color('gray')
                    ->visible(fn (AfastamentoSolicitacao $record): bool => self::canGerenciarCobertura() && $record->coberturasPlantao()->whereIn('status', ['sugerida', 'aprovada'])->exists())
                    ->action(fn (AfastamentoSolicitacao $record) => self::executar(function () use ($record): void {
                        $record->coberturasPlantao()
                            ->whereIn('status', ['sugerida', 'aprovada'])
                            ->update(['status' => 'cancelada']);

                        app(\App\Services\Plantao\PlantaoSubstituicaoService::class)->reverter($record);
                    }, 'Cobertura cancelada')),
                Actions\Action::make('analise')
                    ->label('Ver análise inteligente')
                    ->modalHeading('Análise inteligente')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (AfastamentoSolicitacao $record) => view('filament.pages.partials.afastamento-analise', [
                        'record' => $record->loadMissing('user'),
                        'conflitos' => app(AfastamentoConflictService::class)->detectar($record),
                        'analisePrioridade' => app(AfastamentoPrioridadeService::class)->analisarConflitosPorPrioridade($record),
                        'sugestoes' => app(AfastamentoSuggestionService::class)->sugerir($record),
                        'coberturas' => app(AfastamentoOperacionalService::class)->servidoresDisponiveisParaCobertura($record),
                        'coberturaSelecionadaId' => $record->coberturasPlantao()
                            ->whereIn('status', ['sugerida', 'aprovada'])
                            ->latest()
                            ->value('servidor_cobertura_id'),
                    ])),
                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (AfastamentoSolicitacao $record): bool => self::podeExcluirProprioCanceladoOuIndeferido($record)),
            ])
            ->defaultSort('data_inicio', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAfastamentoSolicitacoes::route('/'),
        ];
    }

    private static function atualizarDias(Set $set, ?string $inicio, ?string $fim): void
    {
        if (! $inicio || ! $fim) {
            $set('dias_solicitados', 0);

            return;
        }

        $start = Carbon::parse($inicio)->startOfDay();
        $end = Carbon::parse($fim)->startOfDay();
        $set('dias_solicitados', $end->lt($start) ? 0 : $start->diffInDays($end) + 1);
    }

    private static function deveMostrarAvisoSemPeriodoAquisitivo(Get $get): bool
    {
        $userId = (int) $get('user_id');
        $tipo = (string) $get('tipo_afastamento');

        if (! $userId || $tipo === '' || self::isAtestado($tipo)) {
            return false;
        }

        return ! AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $userId)
            ->where('tipo_afastamento', $tipo)
            ->where('dias_disponiveis', '>', 0)
            ->whereDate('data_aquisicao', '<=', now()->toDateString())
            ->whereIn('status', [
                StatusPeriodoAquisitivo::ADQUIRIDO->value,
                StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO->value,
                StatusPeriodoAquisitivo::APROVADO->value,
            ])
            ->exists();
    }

    private static function isAtestado(?string $tipo): bool
    {
        return TipoAfastamento::tryFrom((string) $tipo) === TipoAfastamento::ATESTADO;
    }

    private static function executar(callable $callback, string $ok): void
    {
        try {
            $callback();
            Notification::make()->title($ok)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title('Ação não permitida')->body(collect($exception->errors())->flatten()->first())->danger()->send();
            throw $exception;
        }
    }

    private static function isGestor(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('chefia') || $user->hasRole('dpc'));
    }

    private static function canGerenciarCobertura(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasAnyRole(['admin', 'super_admin']) ||
            $user->can('GerenciarCobertura:AfastamentoSolicitacao') ||
            $user->can('Update:AfastamentoSolicitacao')
        );
    }

    private static function podeExcluirProprioCanceladoOuIndeferido(AfastamentoSolicitacao $record): bool
    {
        return (int) $record->user_id === (int) auth()->id()
            && in_array($record->status, [StatusAfastamento::CANCELADO, StatusAfastamento::INDEFERIDO], true);
    }
}
