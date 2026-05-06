<?php

namespace App\Filament\Resources\PlantaoEquipes;

use App\Filament\Resources\PlantaoEquipes\Pages;
use App\Models\PlantaoEquipe;
use App\Services\Plantao\PlantaoEquipeService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PlantaoEquipeResource extends Resource
{
    protected static ?string $model = PlantaoEquipe::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Equipes de Plantão';
    protected static ?string $modelLabel = 'Equipe de plantão';
    protected static ?string $pluralModelLabel = 'Equipes de plantão';
    protected static ?string $slug = 'plantao-equipes';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\Toggle::make('ativo')->default(true),
            Forms\Components\Textarea::make('observacao')->columnSpanFull(),
            Forms\Components\Repeater::make('servidores')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('funcao_plantao')
                        ->label('Função')
                        ->options(['ipc_plantao' => 'IPC Plantão', 'epc_plantao' => 'EPC Plantão'])
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('user_id')
                        ->label('Servidor')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->rules(fn (Get $get): array => [
                            function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $itens = $get('../../servidores');
                                if (! is_array($itens)) {
                                    return;
                                }
                                $ocorrencias = collect($itens)
                                    ->filter(fn (array $item): bool => (string) ($item['user_id'] ?? '') === (string) $value)
                                    ->count();
                                if ($ocorrencias > 1) {
                                    $fail('Este servidor já foi adicionado à equipe.');
                                }
                            },
                        ]),
                    Forms\Components\Toggle::make('ativo')->default(true),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable(),
                Tables\Columns\TextColumn::make('servidores_count')->counts('servidores')->label('Servidores'),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([
                Actions\Action::make('validar')
                    ->label('Validar equipe')
                    ->action(function (PlantaoEquipe $record): void {
                        app(PlantaoEquipeService::class)->validarEquipe($record);
                        Notification::make()->title('Equipe válida')->success()->send();
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlantaoEquipes::route('/')];
    }
}
