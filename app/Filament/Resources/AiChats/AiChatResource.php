<?php

namespace App\Filament\Resources\AiChats;

use App\Filament\Resources\AiChats\Pages;
use App\Models\AiChat;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AiChatResource extends Resource
{
    protected static ?string $model = AiChat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Administração do Sistema';

    protected static ?string $navigationLabel = 'Histórico de Chats IA';

    protected static ?string $modelLabel = 'Chat IA';

    protected static ?string $pluralModelLabel = 'Chats IA';

    protected static ?string $slug = 'ai-chats';

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    /**
     * super_admin/admin vê todos; demais usuários só veem os próprios.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');

        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        // Chats são gerenciados pela página ChatIa; formulário não é utilizado.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $isAdmin = (bool) auth()->user()?->hasAnyRole(['super_admin', 'admin']);

        $columns = [];

        if ($isAdmin) {
            $columns[] = Tables\Columns\TextColumn::make('user.name')
                ->label('Usuário')
                ->searchable()
                ->sortable();
        }

        $columns[] = Tables\Columns\TextColumn::make('title')
            ->label('Título')
            ->searchable()
            ->placeholder('(sem título)')
            ->limit(60);

        $columns[] = Tables\Columns\TextColumn::make('messages_count')
            ->label('Mensagens')
            ->counts('messages')
            ->sortable();

        $columns[] = Tables\Columns\TextColumn::make('last_message_at')
            ->label('Última mensagem (GMT-3)')
            ->dateTime('d/m/Y H:i')
            ->timezone('America/Sao_Paulo')
            ->sortable();

        $columns[] = Tables\Columns\TextColumn::make('created_at')
            ->label('Iniciado em (GMT-3)')
            ->dateTime('d/m/Y H:i')
            ->timezone('America/Sao_Paulo')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $filters = [];

        if ($isAdmin) {
            $filters[] = Tables\Filters\SelectFilter::make('user_id')
                ->label('Usuário')
                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable();
        }

        $filters[] = Tables\Filters\Filter::make('periodo')
            ->label('Período')
            ->form([
                Forms\Components\DatePicker::make('de')
                    ->label('De')
                    ->native(false),
                Forms\Components\DatePicker::make('ate')
                    ->label('Até')
                    ->native(false),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['de'] ?? null, fn (Builder $q, $v) => $q->whereDate('last_message_at', '>=', $v))
                    ->when($data['ate'] ?? null, fn (Builder $q, $v) => $q->whereDate('last_message_at', '<=', $v));
            });

        return $table
            ->columns($columns)
            ->filters($filters)
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make()
                    ->label('Excluir selecionados'),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiChats::route('/'),
            'view' => Pages\ViewAiChat::route('/{record}'),
        ];
    }
}
