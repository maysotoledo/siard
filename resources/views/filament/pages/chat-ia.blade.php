<x-filament-panels::page>
    @php
        $formatarRespostaIa = static function (?string $content): string {
            $parts = preg_split('/(\*\*.+?\*\*)/s', (string) $content, -1, PREG_SPLIT_DELIM_CAPTURE);

            $html = collect($parts)
                ->map(function (string $part): string {
                    if (str_starts_with($part, '**') && str_ends_with($part, '**') && mb_strlen($part) >= 4) {
                        return '<strong>' . e(mb_substr($part, 2, -2)) . '</strong>';
                    }

                    return e($part);
                })
                ->implode('');

            return nl2br($html);
        };
    @endphp

    <div
        class="flex flex-col gap-4"
        @if ($this->aguardandoResposta) wire:poll.2000ms="verificarResposta" @endif
        x-data="{
            observer: null,
            observerTarget: null,
            setupAutoScroll() {
                this.scheduleScroll();
                this.observeMessages();
            },
            observeMessages() {
                this.$nextTick(() => {
                    const el = this.$refs.messages;

                    if (! el || this.observerTarget === el) {
                        return;
                    }

                    this.observer?.disconnect();
                    this.observerTarget = el;
                    this.observer = new MutationObserver(() => this.scheduleScroll());
                    this.observer.observe(el, {
                        attributes: true,
                        childList: true,
                        subtree: true,
                    });
                });
            },
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.messages;
                    const bottom = this.$refs.messagesBottom;

                    if (el) {
                        el.scrollTop = el.scrollHeight;
                    }

                    if (bottom) {
                        bottom.scrollIntoView({ block: 'end' });
                    }
                });
            },
            scheduleScroll() {
                this.observeMessages();
                this.scrollToBottom();
                requestAnimationFrame(() => this.scrollToBottom());
                setTimeout(() => this.scrollToBottom(), 50);
                setTimeout(() => this.scrollToBottom(), 150);
                setTimeout(() => this.scrollToBottom(), 500);
            }
        }"
        x-init="setupAutoScroll()"
        x-on:livewire:update.window="scheduleScroll()"
    >
        {{-- Área de mensagens --}}
        <div
            id="chat-messages"
            x-ref="messages"
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
                                    {!! $formatarRespostaIa($msg['content']) !!}
                                </div>
                                <p class="mt-1 text-left text-xs text-gray-500">
                                    {{ $msg['created_at'] }}
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Indicador "Pensando..." — durante o envio (HTTP) OU enquanto aguarda o job (polling) --}}
            <div
                wire:loading wire:target="enviar"
                class="flex justify-start"
            >
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

            {{-- Indicador "Aguardando IA..." — aparece entre o retorno do HTTP e a resposta do job --}}
            @if ($this->aguardandoResposta)
                <div class="flex justify-start">
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
                                <span style="opacity:0.75">Aguardando IA...</span>
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            <div x-ref="messagesBottom" class="h-px"></div>
        </div>

        {{-- Área de entrada --}}
        <form wire:submit="enviar" x-on:submit="scheduleScroll()" class="flex flex-col gap-2">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <textarea
                        wire:model="mensagem"
                        rows="3"
                        placeholder="Digite sua mensagem..."
                        class="chat-ia-textarea w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); scheduleScroll(); $wire.enviar().then(() => scheduleScroll()) }"
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
