<?php

namespace App\Filament\Resources\InvestigationContexts;

use App\Filament\Resources\InvestigationContexts\Pages\CreateInvestigationContext;
use App\Filament\Resources\InvestigationContexts\Pages\EditInvestigationContext;
use App\Filament\Resources\InvestigationContexts\Pages\ListInvestigationContexts;
use App\Models\AnaliseInvestigation;
use App\Models\InvestigationContext;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InvestigationContextResource extends Resource
{
    protected static ?string $model = InvestigationContext::class;

    protected static string|BackedEnum|null $navigationIcon   = 'heroicon-o-document-text';
    protected static ?string               $navigationLabel  = 'Contextos da investigação';
    protected static string|UnitEnum|null  $navigationGroup  = 'Investigação IA';
    protected static ?int                  $navigationSort   = 10;
    protected static ?string               $slug             = 'investigation-contexts';
    protected static ?string               $modelLabel       = 'Contexto da investigação';
    protected static ?string               $pluralModelLabel = 'Contextos da investigação';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['analiseInvestigation', 'user'])
            ->where('user_id', auth()->id());
    }

    public static function canView(Model $record): bool
    {
        return (int) $record->user_id === (int) auth()->id();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Investigação')
                ->schema([
                    Forms\Components\Select::make('analise_investigation_id')
                        ->label('Investigação')
                        ->options(function () {
                            return AnaliseInvestigation::query()
                                ->where('user_id', auth()->id())
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($inv) => [
                                    $inv->id => sprintf(
                                        '#%d — %s — %s',
                                        $inv->id,
                                        strtoupper($inv->source ?? '?'),
                                        $inv->name,
                                    ),
                                ]);
                        })
                        ->searchable()
                        ->nullable()
                        ->required(),
                ]),

            \Filament\Schemas\Components\Section::make('Boletim de Ocorrência')
                ->schema([
                    Forms\Components\FileUpload::make('arquivo_path')
                        ->label('Upload do BO')
                        ->disk('local')
                        ->directory('investigation-contexts')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(20480)
                        ->storeFileNamesIn('arquivo_original')
                        ->required()
                        ->helperText('PDF, JPG, PNG ou WEBP — máx. 20 MB.'),

                    Forms\Components\Textarea::make('texto_extraido')
                        ->label('Texto do BO')
                        ->rows(10)
                        ->columnSpanFull()
                        ->helperText('Preenchido automaticamente ao salvar (PDF com texto). Para PDF escaneado ou imagem, cole o texto aqui.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('analiseInvestigation.name')
                    ->label('Investigação')
                    ->searchable()
                    ->limit(40)
                    ->description(fn ($record) => $record->analise_investigation_id
                        ? '#' . $record->analise_investigation_id . ' — ' . strtoupper($record->analiseInvestigation?->source ?? '')
                        : null),

                Tables\Columns\TextColumn::make('arquivo_original')
                    ->label('BO')
                    ->formatStateUsing(fn ($state) => $state ?: '— Sem arquivo')
                    ->limit(30),

                Tables\Columns\TextColumn::make('texto_extraido')
                    ->label('Texto')
                    ->formatStateUsing(fn ($state) => $state ? '✔ Preenchido' : '— Vazio')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListInvestigationContexts::route('/'),
            'create' => CreateInvestigationContext::route('/create'),
            'edit'   => EditInvestigationContext::route('/{record}/edit'),
        ];
    }
}
