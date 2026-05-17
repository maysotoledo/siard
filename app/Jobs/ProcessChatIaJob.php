<?php

namespace App\Jobs;

use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Services\AI\AiChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessChatIaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Até 3 minutos para o provedor de IA responder. */
    public int $timeout = 180;

    /** Sem retry automático — erros são exibidos ao usuário. */
    public int $tries = 1;

    public function __construct(
        public readonly int $chatId,
        public readonly int $mensagemPlaceholderId,
        public readonly string $pergunta,
    ) {}

    public function handle(AiChatService $service): void
    {
        $placeholder = AiChatMessage::find($this->mensagemPlaceholderId);

        if (! $placeholder) {
            return;
        }

        $chat = AiChat::find($this->chatId);

        if (! $chat) {
            $this->marcarFalha($placeholder, 'Conversa não encontrada.');

            return;
        }

        try {
            $resposta = $service->enviar($chat);

            $placeholder->forceFill([
                'content' => $resposta,
                'metadata' => ['status' => 'done'],
            ])->save();

            $chat->update(['last_message_at' => now()]);
        } catch (Throwable $e) {
            $this->marcarFalha($placeholder, '⚠️ Erro ao processar resposta: ' . $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        $placeholder = AiChatMessage::find($this->mensagemPlaceholderId);

        if ($placeholder) {
            $this->marcarFalha($placeholder, '⚠️ Tempo limite excedido ao aguardar a IA. Tente novamente.');
        }
    }

    private function marcarFalha(AiChatMessage $msg, string $erro): void
    {
        $msg->forceFill([
            'content' => $erro,
            'metadata' => ['status' => 'done', 'erro' => true],
        ])->save();
    }
}
