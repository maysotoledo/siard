<?php

namespace App\Filament\Resources\PlantaoEscalas;

use App\Enums\PlantaoStatus;
use App\Filament\Resources\PlantaoEscalas\Pages;
use App\Models\PlantaoEquipe;
use App\Models\PlantaoEscala;
use App\Models\User;
use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoEscalaService;
use App\Services\Plantao\PlantaoPdfService;
use App\Services\Plantao\PlantaoPermutaService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PlantaoEscalaResource extends Resource
{
    protected static ?string $model = PlantaoEscala::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Escala de Plantão';
    protected static ?string $modelLabel = 'Escala de plantão';
    protected static ?string $pluralModelLabel = 'Escalas de plantão';
    protected static ?string $slug = 'plantao-escalas';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\DatePicker::make('data_plantao')->required()->native(false),
            Forms\Components\Select::make('equipe_id')->relationship('equipe', 'nome')->searchable()->preload()->required(),
            Forms\Components\TimePicker::make('horario_inicio')->default('07:00')->seconds(false),
            Forms\Components\TimePicker::make('horario_fim')->default('07:00')->seconds(false),
            Forms\Components\Select::make('cqh_pessoa')->label('CQH Geral')->options(fn () => app(PlantaoCqhService::class)->cqhOptions())->searchable()->preload(),
            Forms\Components\Select::make('status')->options(PlantaoStatus::options())->default(PlantaoStatus::PREVISTA->value),
            Forms\Components\Textarea::make('observacao')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('data_plantao')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('equipe.nome')->label('Equipe')->searchable(),
                Tables\Columns\TextColumn::make('cqhGeral')
                    ->label('CQH')
                    ->state(fn (PlantaoEscala $record): string => $record->cqhGeral ? app(PlantaoCqhService::class)->nomePessoa($record->cqhGeral) : '-'),
                Tables\Columns\TextColumn::make('status')->formatStateUsing(fn ($state) => $state?->label())->badge(),
            ])
            ->recordActions([
                Actions\Action::make('permutar')
                    ->label('Permutar')
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalSubmitActionLabel('Permutar')
                    ->visible(fn (): bool => static::canPlantao('permutar_plantao') || static::canPlantao('permutar_cqh'))
                    ->schema([
                        Forms\Components\Select::make('tipo_funcao')->options(['ipc_plantao' => 'IPC', 'epc_plantao' => 'EPC', 'cqh_geral' => 'CQH Geral'])->required(),
                        Forms\Components\Select::make('servidor_original_id')->label('Original')->options(fn () => static::pessoasPermutaOptions())->searchable()->required(),
                        Forms\Components\Select::make('servidor_substituto_id')->label('Substituto')->options(fn () => static::pessoasPermutaOptions())->searchable()->required(),
                        Forms\Components\Textarea::make('motivo'),
                    ])
                    ->action(function (array $data, PlantaoEscala $record): void {
                        try {
                            app(PlantaoPermutaService::class)->permutar($record->id, $data['servidor_original_id'], $data['servidor_substituto_id'], $data['tipo_funcao'], $data['motivo'] ?? null);
                            Notification::make()->title('Permuta registrada')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Permuta não permitida')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            throw $exception;
                        }
                    }),
                Actions\EditAction::make(),
            ])
            ->defaultSort('data_plantao');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlantaoEscalas::route('/')];
    }

    public static function gerarEscalaActions(): array
    {
        return [
            Actions\Action::make('calendario')
                ->label('Calendário')
                ->url(fn () => \App\Filament\Pages\CalendarioPlantaoPage::getUrl())
                ->icon('heroicon-o-calendar')
                ->visible(fn (): bool => static::canPlantao('view_plantao')),
            Actions\Action::make('gerar_plantao')
                ->label('Gerar Escala Plantão')
                ->visible(fn (): bool => static::canPlantao('gerar_escala_plantao'))
                ->schema([
                    Forms\Components\TextInput::make('mes')->numeric()->required()->default(now()->month),
                    Forms\Components\TextInput::make('ano')->numeric()->required()->default(now()->year),
                    Forms\Components\Select::make('equipe_inicial_id')->label('Equipe inicial')->options(fn () => PlantaoEquipe::query()->where('ativo', true)->pluck('nome', 'id')->all())->required(),
                    Forms\Components\Toggle::make('force')->label('Sobrescrever existentes'),
                ])
                ->action(function (array $data): void {
                    $summary = app(PlantaoEscalaService::class)->gerarEscalaMensal((int) $data['mes'], (int) $data['ano'], (int) $data['equipe_inicial_id'], (bool) ($data['force'] ?? false));
                    Notification::make()->title('Escala gerada')->body("Criados: {$summary['criados']} | Ignorados: {$summary['ignorados']}")->success()->send();
                }),
            Actions\Action::make('gerar_cqh')
                ->label('Gerar Escala CQH')
                ->visible(fn (): bool => static::canPlantao('gerar_escala_cqh'))
                ->schema([
                    Forms\Components\TextInput::make('mes')->numeric()->required()->default(now()->month),
                    Forms\Components\TextInput::make('ano')->numeric()->required()->default(now()->year),
                ])
                ->action(function (array $data): void {
                    $total = app(PlantaoCqhService::class)->gerarEscalaCqhMensal((int) $data['mes'], (int) $data['ano']);
                    Notification::make()->title('CQH gerado')->body("Dias atualizados: {$total}")->success()->send();
                }),
            Actions\Action::make('gerar_pdf')
                ->label('Gerar PDF')
                ->visible(fn (): bool => static::canPlantao('gerar_pdf_plantao'))
                ->schema([
                    Forms\Components\TextInput::make('mes')->numeric()->required()->default(now()->month),
                    Forms\Components\TextInput::make('ano')->numeric()->required()->default(now()->year),
                ])
                ->action(function (array $data) {
                    try {
                        app(PlantaoPdfService::class)->validarProntoParaPdf((int) $data['mes'], (int) $data['ano']);

                        return redirect()->route('plantao.pdf', ['mes' => (int) $data['mes'], 'ano' => (int) $data['ano']]);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('PDF não gerado')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ];
    }

    protected static function canPlantao(string $permission): bool
    {
        $user = Auth::user();

        return (bool) ($user && ($user->can($permission) || $user->hasAnyRole(['admin', 'super_admin'])));
    }

    protected static function pessoasPermutaOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => ['user:'.$user->id => $user->name])
            ->all() + app(PlantaoCqhService::class)->cqhOptions();
    }
}
