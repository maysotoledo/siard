<?php

namespace App\Filament\Pages;

use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Services\Ollama\OllamaChatService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use UnitEnum;

class ChatIa extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Chat IA';

    protected static ?string $title = 'Chat IA';

    protected static ?string $slug = 'chat-ia';

    protected string $view = 'filament.pages.chat-ia';

    public string $mensagem = '';

    public ?int $chatAtualId = null;

    public array $historico = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Inteligência Artificial';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function mount(): void
    {
        $chat = AiChat::query()
            ->where('user_id', auth()->id())
            ->latest('last_message_at')
            ->first();

        if ($chat) {
            $this->chatAtualId = $chat->id;
            $this->carregarHistorico();
        }
    }

    public function enviar(): void
    {
        $this->validate([
            'mensagem' => ['required', 'string', 'min:1', 'max:8000'],
        ]);

        $textoUsuario = trim($this->mensagem);
        $this->mensagem = '';

        // 1. Cria ou recupera a conversa
        $chat = $this->obterOuCriarChat($textoUsuario);
        $this->chatAtualId = $chat->id;

        // 2. Salva mensagem do usuário
        AiChatMessage::create([
            'ai_chat_id' => $chat->id,
            'role'       => 'user',
            'content'    => $textoUsuario,
        ]);

        // 3. Chama o Ollama (wire:loading mostra o indicador enquanto isso)
        $resposta = app(OllamaChatService::class)->enviar($chat, $textoUsuario);

        // 4. Salva resposta da IA
        AiChatMessage::create([
            'ai_chat_id' => $chat->id,
            'role'       => 'assistant',
            'content'    => $resposta,
        ]);

        // 5. Atualiza last_message_at e recarrega tela
        $chat->update(['last_message_at' => now()]);
        $this->carregarHistorico();
    }

    public function novaConversa(): void
    {
        $this->chatAtualId = null;
        $this->historico   = [];
        $this->mensagem    = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nova_conversa')
                ->label('Nova conversa')
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->action('novaConversa'),
        ];
    }

    private function obterOuCriarChat(string $primeiraMensagem): AiChat
    {
        if ($this->chatAtualId) {
            $chat = AiChat::query()
                ->where('id', $this->chatAtualId)
                ->where('user_id', auth()->id())
                ->first();

            if ($chat) {
                return $chat;
            }
        }

        return AiChat::create([
            'user_id'         => auth()->id(),
            'title'           => mb_substr($primeiraMensagem, 0, 60),
            'last_message_at' => now(),
        ]);
    }

    private function carregarHistorico(): void
    {
        if (! $this->chatAtualId) {
            $this->historico = [];

            return;
        }

        $this->historico = AiChatMessage::query()
            ->where('ai_chat_id', $this->chatAtualId)
            ->where('role', '!=', 'system')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiChatMessage $msg): array => [
                'role'       => $msg->role,
                'content'    => $msg->content,
                'created_at' => $msg->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ])
            ->toArray();
    }
}
