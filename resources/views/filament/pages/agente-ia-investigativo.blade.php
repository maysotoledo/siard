<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Selecionar investigacao
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Investigacao
                    </label>

                    <select
                        wire:model.live="analise_investigation_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="">Selecione uma investigacao...</option>

                        @foreach ($this->getInvestigacoesDisponiveis() as $investigation)
                            <option value="{{ $investigation->id }}">
                                {{ $investigation->name ?? ('Investigacao #' . $investigation->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Alvo da investigacao
                    </label>

                    <select
                        wire:model="analise_run_id"
                        @disabled(! $analise_investigation_id)
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:opacity-60"
                    >
                        <option value="">Selecione um alvo...</option>

                        @foreach ($this->getAlvosDisponiveis() as $run)
                            <option value="{{ $run->id }}">
                                {{ $run->target ?? ('Alvo #' . $run->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Pergunta livre ao agente
            </x-slot>

            <div class="space-y-4">
                <textarea
                    wire:model="perguntaLivre"
                    rows="4"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    placeholder="Pergunte ao agente sobre o alvo selecionado..."
                ></textarea>

                <x-filament::button
                    wire:click="gerarAnalise('pergunta_livre')"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-paper-airplane"
                >
                    Perguntar ao agente
                </x-filament::button>

                <div wire:loading wire:target="gerarAnalise" class="text-sm text-gray-500 dark:text-gray-400">
                    Gerando analise com IA local. Aguarde...
                </div>
            </div>
        </x-filament::section>

        @if ($ultimaAnaliseId)
            <div @if ($this->deveAtualizarUltimaAnalise()) wire:poll.5s="atualizarUltimaAnalise" @endif>
                <x-filament::section>
                <x-slot name="heading">
                    Status da analise
                </x-slot>

                <x-slot name="description">
                    Tipo: {{ $ultimoTipo }} | Modelo: {{ $this->getModeloIaConfigurado() ?: 'nao configurado' }}
                </x-slot>

                    @if ($ultimoStatus === 'queued')
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            A analise foi enviada para a fila e esta aguardando processamento.
                        </div>
                    @elseif ($ultimoStatus === 'processing')
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            A IA esta processando a solicitacao. Esta tela atualiza automaticamente.
                        </div>
                    @elseif ($ultimoStatus === 'failed')
                        <div class="text-sm text-red-600 dark:text-red-400 whitespace-pre-wrap">
                            Falha ao gerar a analise.
                            {{ $ultimoErro ? "\n" . $ultimoErro : '' }}
                        </div>
                    @elseif ($ultimaResposta)
                        <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap">
                            {{ $ultimaResposta }}
                        </div>
                    @endif

                    @if (in_array($ultimoStatus, ['queued', 'processing', 'completed'], true))
                        <div class="mt-4 space-y-2">
                            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                                <span>Progresso</span>
                                <span>{{ $ultimoProgresso }}%</span>
                            </div>

                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                <div
                                    class="h-2 rounded-full bg-primary-600 transition-all duration-500"
                                    style="width: {{ max(0, min(100, $ultimoProgresso)) }}%;"
                                ></div>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                Orientacao de uso
            </x-slot>

            <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                <p>
                    Este agente utiliza IA local via Ollama. A analise gerada e apenas apoio tecnico
                    e deve ser validada pelo investigador.
                </p>

                <p>
                    A IA nao deve ser usada para afirmar autoria, culpa ou conclusao definitiva.
                    Ela deve apontar padroes, indicios, limitacoes e diligencias possiveis.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
