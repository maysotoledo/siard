<?php

namespace App\Services\AI;

use App\Models\AiChat;

class AiChatService
{
    public function __construct(
        private readonly AiManager $ai,
    ) {}

    public function enviar(AiChat $chat): string
    {
        $this->prepararExecucaoLonga();

        return $this->ai->generate($this->montarPrompt($chat), [
            'temperature' => 0.3,
            'top_p' => 0.9,
            'num_predict' => 600,
        ]);
    }

    private function montarPrompt(AiChat $chat): string
    {
        $historico = $chat->messages()
            ->where('role', '!=', 'system')
            ->where('content', '!=', '')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): string => sprintf(
                "%s:\n%s",
                $message->role === 'assistant' ? 'ASSISTENTE' : 'USUARIO',
                trim((string) $message->content),
            ))
            ->implode("\n\n");

        return <<<PROMPT
Você é um assistente inteligente integrado ao SIARD.

SOBRE O SIARD:
O SIARD é um sistema de apoio à investigação e análise telemática, com recursos para análise de logs digitais, IPs, vínculos, relatórios, contextos investigativos e inteligência artificial aplicada à investigação policial.

REGRAS:
- Responda sempre em português do Brasil.
- Seja claro, objetivo e profissional.
- Quando não souber, diga claramente em vez de inventar.
- Não conclua autoria ou culpa criminal; use termos como "indício", "sugere" ou "necessita validação".
- Use apenas o histórico abaixo como contexto da conversa.

HISTORICO DA CONVERSA:
{$historico}

RESPONDA A ÚLTIMA MENSAGEM DO USUÁRIO.
PROMPT;
    }

    private function prepararExecucaoLonga(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        @ini_set('max_execution_time', '180');
    }
}
