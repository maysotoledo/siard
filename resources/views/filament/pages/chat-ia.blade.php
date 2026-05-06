<x-filament-panels::page>
    <div
        class="flex flex-col gap-4"
        x-data="{
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = document.getElementById('chat-messages');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        }"
        x-init="scrollToBottom()"
        x-on:livewire:update.window="scrollToBottom()"
    >
        {{-- Área de mensagens --}}
        <div
            id="chat-messages"
            class="flex flex-col gap-3 overflow-y-auto rounded-xl border p-4"
            style="min-height: 420px; max-height: 62vh;"
        >
            @if (empty($this->historico))
                <div class="flex flex-1 flex-col items-center justify-center gap-2 py-16 text-center">
                    <x-filament::icon
                        icon="heroicon-o-chat-bubble-left-right"
                        class="h-12 w-12 text-gray-300 dark:text-gray-700"
                    />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nenhuma mensagem ainda. Inicie uma conversa abaixo.
                    </p>
                </div>
            @else
                @foreach ($this->historico as $msg)
                    @if ($msg['role'] === 'user')
                        {{-- Balão do usuário — direita --}}
                        <div class="flex justify-end">
                            <div class="max-w-[80%]">
                                <div class="rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm text-white shadow-sm">
                                    {!! nl2br(e($msg['content'])) !!}
                                </div>
                                <p class="mt-1 text-right text-xs text-gray-500">
                                    {{ $msg['created_at'] }}
                                </p>
                            </div>
                        </div>
                    @else
                        {{-- Balão da IA — esquerda --}}
                        <div class="flex justify-start">
                            <div class="max-w-[80%]">
                                <div class="mb-1 flex items-center gap-1.5">
                                    <x-filament::icon icon="heroicon-o-cpu-chip" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">IA</span>
                                </div>
                                <div class="chat-ia-balloon-ia rounded-2xl rounded-bl-sm px-4 py-2.5 text-sm shadow-sm">
                                    {!! nl2br(e($msg['content'])) !!}
                                </div>
                                <p class="mt-1 text-left text-xs text-gray-500">
                                    {{ $msg['created_at'] }}
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Indicador "Pensando..." — aparece enquanto o Livewire processa o envio --}}
            <div wire:loading wire:target="enviar" class="flex justify-start">
                <div class="max-w-[80%]">
                    <div class="mb-1 flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-o-cpu-chip" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">IA</span>
                    </div>
                    <div class="chat-ia-balloon-ia rounded-2xl rounded-bl-sm px-5 py-3 shadow-sm">
                        <span class="flex items-center gap-3 text-sm">
                            <span class="flex gap-1.5">
                                <span class="chat-dot"></span>
                                <span class="chat-dot"></span>
                                <span class="chat-dot"></span>
                            </span>
                            <span style="opacity:0.75">Pensando...</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Área de entrada --}}
        <form wire:submit="enviar" class="flex flex-col gap-2">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <textarea
                        wire:model="mensagem"
                        rows="3"
                        placeholder="Digite sua mensagem..."
                        class="chat-ia-textarea w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.enviar() }"
                        wire:loading.attr="disabled"
                        wire:target="enviar"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Enter para enviar · Shift+Enter para nova linha
                    </p>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="enviar"
                    class="mb-6 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg wire:loading wire:target="enviar" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <svg wire:loading.remove wire:target="enviar" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                    Enviar
                </button>
            </div>
        </form>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
