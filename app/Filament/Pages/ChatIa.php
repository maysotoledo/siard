<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessChatIaJob;
use App\Models\AiChat;
use App\Models\AiChatMessage;
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

    /** Ativado enquanto o job ainda não respondeu — controla o wire:poll. */
    public bool $aguardandoResposta = false;

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

            // Retoma polling se havia resposta pendente (ex.: após F5)
            $this->aguardandoResposta = $this->temPendente();
        }
    }

    /**
     * Chamado a cada 2 s pelo wire:poll enquanto $aguardandoResposta = true.
     */
    public function verificarResposta(): void
    {
        if (! $this->aguardandoResposta) {
            return;
        }

        if (! $this->temPendente()) {
            $this->aguardandoResposta = false;
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

        // 3. Placeholder da resposta (status pending)
        $placeholder = AiChatMessage::create([
            'ai_chat_id' => $chat->id,
            'role'       => 'assistant',
            'content'    => '',
            'metadata'   => ['status' => 'pending'],
        ]);

        // 4. Dispara job — retorna imediatamente, sem risco de 504
        ProcessChatIaJob::dispatchAfterResponse($chat->id, $placeholder->id, $textoUsuario);

        // 5. Ativa polling e atualiza tela
        $this->aguardandoResposta = true;
        $this->carregarHistorico();
    }

    public function novaConversa(): void
    {
        $this->chatAtualId       = null;
        $this->historico         = [];
        $this->mensagem          = '';
        $this->aguardandoResposta = false;
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

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function temPendente(): bool
    {
        if (! $this->chatAtualId) {
            return false;
        }

        return AiChatMessage::query()
            ->where('ai_chat_id', $this->chatAtualId)
            ->where('role', 'assistant')
            ->where('metadata->status', 'pending')
            ->exists();
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
                'pending'    => ($msg->metadata['status'] ?? '') === 'pending',
                'erro'       => (bool) ($msg->metadata['erro'] ?? false),
                'created_at' => $msg->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ])
            ->toArray();
    }
}
