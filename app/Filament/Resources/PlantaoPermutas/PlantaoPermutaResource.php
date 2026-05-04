<?php

namespace App\Filament\Resources\PlantaoPermutas;

use App\Filament\Resources\PlantaoPermutas\Pages;
use App\Models\PlantaoEscala;
use App\Models\PlantaoPermuta;
use App\Services\Plantao\PlantaoCalendarService;
use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoPermutaService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PlantaoPermutaResource extends Resource
{
    protected static ?string $model = PlantaoPermuta::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Permutas de Plantão';
    protected static ?string $slug = 'plantao-permutas';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->query(fn (): Builder => PlantaoPermuta::query()
            ->with(['escala', 'escalaDestino', 'servidorOriginal', 'servidorSubstituto'])
            ->where(fn (Builder $query) => $query->whereNull('lado')->orWhere('lado', 'origem')))
        ->columns([
            Tables\Columns\TextColumn::make('data_plantao')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('escalaDestino.data_plantao')->label('Destino')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('tipo_funcao')->badge(),
            Tables\Columns\TextColumn::make('servidorOriginal')
                ->label('Servidor origem')
                ->state(fn (PlantaoPermuta $record): string => $record->servidorOriginal ? app(PlantaoCqhService::class)->nomePessoa($record->servidorOriginal) : '-'),
            Tables\Columns\TextColumn::make('servidorSubstituto')
                ->label('Servidor destino')
                ->state(fn (PlantaoPermuta $record): string => $record->servidorSubstituto ? app(PlantaoCqhService::class)->nomePessoa($record->servidorSubstituto) : '-'),
            Tables\Columns\TextColumn::make('autorizado_em')->dateTime('d/m/Y H:i'),
        ])
        ->recordActions([
            Actions\ViewAction::make()
                ->label('Visualizar')
                ->schema([
                    Forms\Components\Placeholder::make('origem')
                        ->label('Origem')
                        ->content(fn (PlantaoPermuta $record): string => static::resumoOrigem($record)),
                    Forms\Components\Placeholder::make('destino')
                        ->label('Destino')
                        ->content(fn (PlantaoPermuta $record): string => static::resumoDestino($record)),
                    Forms\Components\Placeholder::make('tipo')
                        ->label('Tipo')
                        ->content(fn (PlantaoPermuta $record): string => (string) $record->tipo_funcao),
                    Forms\Components\Placeholder::make('motivo')
                        ->label('Motivo')
                        ->content(fn (PlantaoPermuta $record): string => $record->motivo ?: '-'),
                ]),
            Actions\DeleteAction::make()
                ->label('Excluir')
                ->requiresConfirmation()
                ->action(function (PlantaoPermuta $record): void {
                    if ($record->grupo_permuta) {
                        PlantaoPermuta::query()->where('grupo_permuta', $record->grupo_permuta)->delete();
                        return;
                    }

                    $record->delete();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlantaoPermutas::route('/')];
    }

    public static function permutaSchema(): array
    {
        return [
            Forms\Components\Select::make('tipo_funcao')
                ->options(['ipc_plantao' => 'IPC', 'epc_plantao' => 'EPC', 'cqh_geral' => 'CQH Geral'])
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('servidor_original_id', null);
                    $set('servidor_substituto_id', null);
                })
                ->required(),
            Forms\Components\Select::make('escala_origem_id')
                ->label('Dia de origem')
                ->options(fn (): array => static::diasOptions())
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('servidor_original_id', null);
                    $set('escala_destino_id', null);
                    $set('servidor_substituto_id', null);
                })
                ->searchable()
                ->required(),
            Forms\Components\Select::make('servidor_original_id')
                ->label('Servidor de origem')
                ->options(fn (Get $get): array => static::servidoresDaEscala((int) ($get('escala_origem_id') ?? 0), $get('tipo_funcao')))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('escala_destino_id')
                ->label('Dia de destino')
                ->options(fn (Get $get): array => static::diasOptions((int) ($get('escala_origem_id') ?? 0)))
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('servidor_substituto_id', null))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('servidor_substituto_id')
                ->label('Servidor de destino')
                ->options(fn (Get $get): array => static::servidoresDaEscala((int) ($get('escala_destino_id') ?? 0), $get('tipo_funcao')))
                ->searchable()
                ->required(),
            Forms\Components\Textarea::make('motivo')->columnSpanFull(),
        ];
    }

    private static function diasOptions(int $ignoreId = 0): array
    {
        return PlantaoEscala::query()
            ->when($ignoreId > 0, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->orderBy('data_plantao')
            ->get()
            ->mapWithKeys(fn (PlantaoEscala $escala): array => [
                $escala->id => $escala->data_plantao?->format('d/m/Y').' - '.$escala->data_plantao?->translatedFormat('l'),
            ])
            ->all();
    }

    private static function servidoresDaEscala(int $escalaId, ?string $tipoFuncao): array
    {
        if ($escalaId <= 0) {
            return [];
        }

        $escala = PlantaoEscala::query()
            ->with(['equipe.servidores.user', 'cqhGeral', 'permutas.servidorOriginal', 'permutas.servidorSubstituto'])
            ->find($escalaId);

        if (! $escala instanceof PlantaoEscala) {
            return [];
        }

        $membros = app(PlantaoCalendarService::class)->membrosFinais($escala);
        $options = [];

        if ($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'ipc_plantao') {
            foreach ($membros['ipc'] as $pessoa) {
                if ($pessoa instanceof Model) {
                    $options[app(PlantaoCqhService::class)->keyFor($pessoa)] = 'IPC: '.app(PlantaoCqhService::class)->nomePessoa($pessoa);
                }
            }
        }

        if ($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'epc_plantao') {
            foreach ($membros['epc'] as $pessoa) {
                if ($pessoa instanceof Model) {
                    $options[app(PlantaoCqhService::class)->keyFor($pessoa)] = 'EPC: '.app(PlantaoCqhService::class)->nomePessoa($pessoa);
                }
            }
        }

        if (($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'cqh_geral') && $escala->cqhGeral) {
            $options[app(PlantaoCqhService::class)->keyFor($escala->cqhGeral)] = 'CQH: '.app(PlantaoCqhService::class)->nomePessoa($escala->cqhGeral);
        }

        return $options;
    }

    private static function resumoOrigem(PlantaoPermuta $record): string
    {
        return ($record->escala?->data_plantao?->format('d/m/Y') ?? '-') .
            ' - ' . ($record->servidorOriginal ? app(PlantaoCqhService::class)->nomePessoa($record->servidorOriginal) : '-');
    }

    private static function resumoDestino(PlantaoPermuta $record): string
    {
        return ($record->escalaDestino?->data_plantao?->format('d/m/Y') ?? '-') .
            ' - ' . ($record->servidorSubstituto ? app(PlantaoCqhService::class)->nomePessoa($record->servidorSubstituto) : '-');
    }
}
