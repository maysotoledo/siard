<?php

namespace App\Filament\Resources\AiChats\Pages;

use App\Filament\Resources\AiChats\AiChatResource;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAiChat extends ViewRecord
{
    protected static string $resource = AiChatResource::class;

    protected string $view = 'filament.resources.ai-chats.pages.view-ai-chat';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getMensagens(): array
    {
        /** @var AiChat $record */
        $record = $this->record;

        return AiChatMessage::query()
            ->where('ai_chat_id', $record->id)
            ->where('role', '!=', 'system')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiChatMessage $msg): array => [
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
            ])
            ->toArray();
    }
}
