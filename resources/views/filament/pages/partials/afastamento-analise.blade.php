<div class="space-y-6">
    <div>
        <div class="text-sm font-semibold text-gray-700">Função operacional</div>
        <div class="mt-2 rounded-lg border p-3 text-sm">
            <div>Servidor: {{ $record->user?->name ?? '-' }}</div>
            <div>Função: {{ $record->user?->funcao_operacional?->label() ?? '-' }}</div>
            <div>Grupo: {{ $record->user?->funcao_operacional?->grupoOperacional() === 'plantao' ? 'Plantão' : 'Expediente' }}</div>
            <div>Prioridade: {{ $record->prioridade_score ?? '-' }} / {{ $record->prioridade_nivel ?? '-' }}</div>
            <div>Ranking: {{ $record->prioridade_posicao ?? '-' }}</div>
            <div>{{ $record->prioridade_motivo ?? 'Prioridade ainda não calculada.' }}</div>
        </div>
    </div>

    <div>
        <div class="text-sm font-semibold text-gray-700">Conflitos encontrados</div>
        <div class="mt-2 space-y-2">
            @forelse($conflitos as $conflito)
                <div class="rounded-lg border p-3">
                    <div class="font-semibold">{{ strtoupper($conflito['nivel'] ?? '-') }} - {{ $conflito['mensagem'] ?? '-' }}</div>
                    <div class="text-sm text-gray-600">{{ $conflito['sugestao'] ?? '-' }}</div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Nenhum conflito crítico detectado.</div>
            @endforelse
        </div>
    </div>

    <div>
        <div class="text-sm font-semibold text-gray-700">Fila de prioridade por conflito</div>
        <div class="mt-2 rounded-lg border p-3 text-sm">
            <div class="font-semibold">Critérios de desempate</div>
            <ol class="mt-1 list-decimal space-y-1 pl-5 text-gray-600">
                @foreach(($analisePrioridade['criterios'] ?? []) as $criterio)
                    <li>{{ preg_replace('/^\d+\.\s*/', '', $criterio) }}</li>
                @endforeach
            </ol>

            <div class="mt-3 text-gray-700">{{ $analisePrioridade['mensagem'] ?? 'Nenhuma análise de prioridade disponível.' }}</div>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                    <tr class="border-b">
                        <th class="py-2 pr-2">#</th>
                        <th class="py-2 pr-2">Servidor</th>
                        <th class="py-2 pr-2">Período</th>
                        <th class="py-2 pr-2">Carreira</th>
                        <th class="py-2 pr-2">Unidade</th>
                        <th class="py-2 pr-2">Solicitou</th>
                        <th class="py-2 pr-2">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($analisePrioridade['ranking'] ?? []) as $linha)
                        <tr class="border-b {{ ($linha['eh_solicitacao_atual'] ?? false) ? 'font-semibold' : '' }}">
                            <td class="py-2 pr-2">{{ $linha['posicao'] ?? '-' }}</td>
                            <td class="py-2 pr-2">
                                {{ $linha['servidor'] ?? '-' }}
                                @if($linha['eh_solicitacao_atual'] ?? false)
                                    <span class="text-gray-500">(solicitação atual)</span>
                                @endif
                                <div class="text-gray-500">{{ $linha['motivo'] ?? '' }}</div>
                            </td>
                            <td class="py-2 pr-2">{{ $linha['periodo'] ?? '-' }}</td>
                            <td class="py-2 pr-2">{{ $linha['data_carreira'] ?? '-' }}</td>
                            <td class="py-2 pr-2">{{ $linha['data_unidade'] ?? '-' }}</td>
                            <td class="py-2 pr-2">{{ $linha['solicitado_em'] ?? '-' }}</td>
                            <td class="py-2 pr-2">{{ $linha['status'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-2 text-gray-500" colspan="7">Nenhum servidor conflitante encontrado.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="text-sm font-semibold text-gray-700">Sugestões alternativas e cobertura</div>
        <div class="mt-2 grid gap-2 md:grid-cols-3">
            @forelse($sugestoes as $sugestao)
                <div class="rounded-lg border p-3 text-sm">{{ $sugestao['label'] ?? '-' }}</div>
            @empty
                <div class="text-sm text-gray-500">Nenhuma sugestão automática disponível.</div>
            @endforelse
        </div>
    </div>

    @if(! empty($coberturas))
        <div>
            <div class="text-sm font-semibold text-gray-700">Servidores IPC expediente disponíveis para cobertura</div>
            <div class="mt-2 grid gap-2 md:grid-cols-3">
                @foreach($coberturas as $nome)
                    <div class="rounded-lg border p-3 text-sm">{{ $nome }}</div>
                @endforeach
            </div>
        </div>
    @endif
</div>
