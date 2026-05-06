<?php

namespace App\Services\Ollama;

use App\Models\AiChat;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaChatService
{
    private string $baseUrl;

    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ollama.url', 'http://localhost:11434'), '/');
        $this->model = (string) config('services.ollama.model', 'llama3.2:3b');
    }

    /**
     * Envia uma mensagem para o Ollama com o histórico completo da conversa.
     * Retorna o conteúdo da resposta ou uma mensagem de erro clara.
     */
    public function enviar(AiChat $chat, string $novasMensagem): string
    {
        $this->prepararExecucaoLonga();

        $messages = $this->montarHistorico($chat, $novasMensagem);

        try {
            $response = Http::timeout(150)
                ->post($this->baseUrl . '/api/chat', [
                    'model' => $this->model,
                    'stream' => false,
                    'messages' => $messages,
                    'options' => [
                        'temperature' => 0.7,
                        'top_p' => 0.9,
                    ],
                ]);

            if ($response->failed()) {
                return sprintf(
                    '⚠️ Ollama retornou HTTP %d. Verifique se o modelo "%s" está disponível em: %s',
                    $response->status(),
                    $this->model,
                    $this->baseUrl,
                );
            }

            $content = trim((string) ($response->json('message.content') ?? ''));

            return $content !== '' ? $content : 'A IA não retornou conteúdo.';
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            // Detecta erro de conexão recusada e dá orientação clara
            if (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Failed to connect') || str_contains($msg, 'Connection refused')) {
                return sprintf(
                    "⚠️ Não foi possível conectar ao Ollama em **%s**.\n\n" .
                    "Verifique:\n" .
                    "1. O Ollama está iniciado? Execute `ollama serve` no terminal.\n" .
                    "2. O endereço no .env (`OLLAMA_URL`) está correto?\n" .
                    "3. Se o app roda via Docker, use `http://host.docker.internal:11434` no .env.",
                    $this->baseUrl,
                );
            }

            return '⚠️ Erro ao comunicar com o Ollama: ' . $msg;
        }
    }

    /**
     * Monta o array de mensagens no formato esperado pelo /api/chat,
     * incluindo o histórico salvo no banco (exceto mensagens do tipo system já resolvidas).
     */
    private function montarHistorico(AiChat $chat, string $novaMensagem): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => <<<SYSTEM
Você é um assistente inteligente integrado ao SACAT.

SOBRE O SACAT:
O SACAT (Sistema de Agendamento de Oitiva e de Análise Telemática) é um sistema de gestão administrativa desenvolvido para a Delegacia de Polícia Civil de Confresa-MT. O sistema foi criado pelo Investigador de Polícia Mayso Toledo e engloba funcionalidades de agendamento de oitivas, análise telemática de dados digitais (WhatsApp, Instagram, Google, Facebook, Apple e outras plataformas), gestão de servidores, escala de plantão, controle de afastamentos e férias, e ferramentas de inteligência artificial aplicadas à investigação policial.

SUAS RESPONSABILIDADES:
- Responder perguntas sobre o sistema SACAT e suas funcionalidades.
- Auxiliar os usuários (investigadores, escrivães e gestores da delegacia) nas tarefas do dia a dia.
- Apoiar análises investigativas com base em dados fornecidos pelo usuário.
- Responder dúvidas gerais de forma clara e objetiva.

REGRAS:
- Responda SEMPRE em português do Brasil.
- Seja claro, objetivo e profissional.
- Quando não souber a resposta, diga claramente ao invés de inventar informações.
- Não conclua autoria ou culpa criminal — use termos como "indício", "sugere" ou "necessita validação".
SYSTEM,
            ],
        ];

        // Carrega histórico do banco — exclui system e placeholders pendentes (content vazio)
        foreach ($chat->messages()->where('role', '!=', 'system')->where('content', '!=', '')->get() as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // Adiciona a nova mensagem do usuário
        $messages[] = [
            'role' => 'user',
            'content' => $novaMensagem,
        ];

        return $messages;
    }

    private function prepararExecucaoLonga(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(150);
        }

        @ini_set('max_execution_time', '150');
    }
}
