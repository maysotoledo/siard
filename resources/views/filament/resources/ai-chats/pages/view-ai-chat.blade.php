<x-filament-panels::page>
    {{-- Informações do chat --}}
    <x-filament::section heading="Informações">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Usuário</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->user?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Título</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->title ?? '(sem título)' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Última mensagem</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">
                    {{ $this->record->last_message_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '-' }}
                </dd>
            </div>
        </dl>
    </x-filament::section>

    {{-- Histórico de mensagens --}}
    <x-filament::section heading="Mensagens da conversa" class="mt-4">
        @php $mensagens = $this->getMensagens(); @endphp

        @if (empty($mensagens))
            <p class="text-sm text-gray-400">Nenhuma mensagem encontrada.</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($mensagens as $msg)
                    @if ($msg['role'] === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-[80%]">
                                <div class="rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2 text-sm text-white shadow-sm">
                                    {!! nl2br(e($msg['content'])) !!}
                                </div>
                                <p class="mt-1 text-right text-xs text-gray-400">
                                    Usuário · {{ $msg['created_at'] }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start">
                            <div class="max-w-[80%]">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <x-filament::icon icon="heroicon-o-cpu-chip" class="h-4 w-4 text-gray-400" />
                                    <span class="text-xs font-medium text-gray-500">IA</span>
                                </div>
                                <div class="rounded-2xl rounded-bl-sm bg-white px-4 py-2 text-sm text-gray-800 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-700">
                                    {!! nl2br(e($msg['content'])) !!}
                                </div>
                                <p class="mt-1 text-left text-xs text-gray-400">{{ $msg['created_at'] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
