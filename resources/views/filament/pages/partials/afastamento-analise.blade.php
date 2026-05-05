<style>
    /* === afastamento-analise — dark mode via seletor .dark do Filament === */
    .afa-wrap                           { display: flex; flex-direction: column; gap: 1.5rem; }
    .afa-section-title                  { font-size: .875rem; font-weight: 600; color: #374151; }
    .afa-card                           { margin-top: .5rem; border: 1px solid #e5e7eb; border-radius: .5rem;
                                          padding: .75rem; font-size: .875rem; color: #1f2937; }
    .afa-card-line                      { margin-bottom: .1rem; }
    .afa-conflict-title                 { font-weight: 600; color: #1f2937; }
    .afa-conflict-sub                   { font-size: .875rem; color: #4b5563; }
    .afa-empty                          { font-size: .875rem; color: #6b7280; }
    .afa-criteria-list                  { margin-top: .25rem; padding-left: 1.25rem; color: #4b5563;
                                          display: flex; flex-direction: column; gap: .25rem; }
    .afa-msg                            { margin-top: .75rem; color: #374151; }
    .afa-table                          { width: 100%; border-collapse: collapse; text-align: left; font-size: .75rem; }
    .afa-table th                       { padding: .5rem .5rem .5rem 0; color: #4b5563;
                                          border-bottom: 1px solid #e5e7eb; }
    .afa-table td                       { padding: .5rem .5rem .5rem 0; color: #1f2937;
                                          border-bottom: 1px solid #e5e7eb; }
    .afa-table tr:last-child td         { border-bottom: none; }
    .afa-table .afa-row-current         { font-weight: 600; }
    .afa-muted                          { color: #6b7280; }
    .afa-grid                           { margin-top: .5rem; display: grid; gap: .5rem; }
    .afa-choice                         { width: 100%; text-align: left; cursor: pointer; background: transparent;
                                          transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease; }
    .afa-choice:hover                   { border-color: #60a5fa; background: #eff6ff; }
    .afa-choice-selected                { border-color: #2563eb; background: #dbeafe; box-shadow: 0 0 0 2px rgba(37, 99, 235, .18); }
    .afa-bubble-mark                    { display: inline-flex; align-items: center; justify-content: center;
                                          width: 1.25rem; height: 1.25rem; border-radius: 9999px;
                                          margin-right: .4rem; font-size: .75rem; font-weight: 700;
                                          color: #fff; background: #2563eb; vertical-align: middle; }
    .afa-choice-meta                    { margin-top: .35rem; font-size: .75rem; color: #6b7280; }
    @media (min-width: 768px) { .afa-grid { grid-template-columns: repeat(3, 1fr); } }

    /* Dark */
    .dark .afa-section-title            { color: #d1d5db; }
    .dark .afa-card                     { border-color: #374151; color: #e5e7eb; }
    .dark .afa-conflict-title           { color: #f3f4f6; }
    .dark .afa-conflict-sub             { color: #9ca3af; }
    .dark .afa-empty                    { color: #9ca3af; }
    .dark .afa-criteria-list            { color: #9ca3af; }
    .dark .afa-msg                      { color: #d1d5db; }
    .dark .afa-table th                 { color: #9ca3af; border-bottom-color: #374151; }
    .dark .afa-table td                 { color: #e5e7eb; border-bottom-color: #374151; }
    .dark .afa-muted                    { color: #9ca3af; }
    .dark .afa-choice:hover             { border-color: #60a5fa; background: rgba(37, 99, 235, .18); }
    .dark .afa-choice-selected          { border-color: #60a5fa; background: rgba(37, 99, 235, .24); }
    .dark .afa-choice-meta              { color: #9ca3af; }
</style>

@php
    $coberturaSelecionadaId = isset($coberturaSelecionadaId) ? (int) $coberturaSelecionadaId : null;
    $selecionarCoberturaAction = $selecionarCoberturaAction ?? null;
@endphp

<div class="afa-wrap">

    {{-- Função operacional --}}
    <div>
        <div class="afa-section-title">Função operacional</div>
        <div class="afa-card">
            <div class="afa-card-line">Servidor: {{ $record->user?->name ?? '-' }}</div>
            <div class="afa-card-line">Função: {{ $record->user?->funcao_operacional?->label() ?? '-' }}</div>
            <div class="afa-card-line">Grupo: {{ $record->user?->funcao_operacional?->grupoOperacional() === 'plantao' ? 'Plantão' : 'Expediente' }}</div>
            <div class="afa-card-line">Prioridade: {{ $record->prioridade_score ?? '-' }} / {{ $record->prioridade_nivel ?? '-' }}</div>
            <div class="afa-card-line">Ranking: {{ $record->prioridade_posicao ?? '-' }}</div>
            <div class="afa-card-line">{{ $record->prioridade_motivo ?? 'Prioridade ainda não calculada.' }}</div>
        </div>
    </div>

    {{-- Conflitos --}}
    <div>
        <div class="afa-section-title">Conflitos encontrados</div>
        <div style="margin-top:.5rem; display:flex; flex-direction:column; gap:.5rem;">
            @forelse($conflitos as $conflito)
                <div class="afa-card" style="padding:.75rem;">
                    <div class="afa-conflict-title">{{ strtoupper($conflito['nivel'] ?? '-') }} — {{ $conflito['mensagem'] ?? '-' }}</div>
                    <div class="afa-conflict-sub">{{ $conflito['sugestao'] ?? '-' }}</div>
                </div>
            @empty
                <div class="afa-empty">Nenhum conflito crítico detectado.</div>
            @endforelse
        </div>
    </div>

    {{-- Fila de prioridade --}}
    <div>
        <div class="afa-section-title">Fila de prioridade por conflito</div>
        <div class="afa-card">
            <div class="afa-conflict-title">Critérios de desempate</div>
            <ol class="afa-criteria-list" style="list-style:decimal;">
                @foreach(($analisePrioridade['criterios'] ?? []) as $criterio)
                    <li>{{ preg_replace('/^\d+\.\s*/', '', $criterio) }}</li>
                @endforeach
            </ol>

            <div class="afa-msg">{{ $analisePrioridade['mensagem'] ?? 'Nenhuma análise de prioridade disponível.' }}</div>

            <div style="margin-top:.75rem; overflow-x:auto;">
                <table class="afa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Servidor</th>
                            <th>Período</th>
                            <th>Carreira</th>
                            <th>Unidade</th>
                            <th>Solicitou</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($analisePrioridade['ranking'] ?? []) as $linha)
                            <tr class="{{ ($linha['eh_solicitacao_atual'] ?? false) ? 'afa-row-current' : '' }}">
                                <td>{{ $linha['posicao'] ?? '-' }}</td>
                                <td>
                                    {{ $linha['servidor'] ?? '-' }}
                                    @if($linha['eh_solicitacao_atual'] ?? false)
                                        <span class="afa-muted">(solicitação atual)</span>
                                    @endif
                                    <div class="afa-muted">{{ $linha['motivo'] ?? '' }}</div>
                                </td>
                                <td>{{ $linha['periodo'] ?? '-' }}</td>
                                <td>{{ $linha['data_carreira'] ?? '-' }}</td>
                                <td>{{ $linha['data_unidade'] ?? '-' }}</td>
                                <td>{{ $linha['solicitado_em'] ?? '-' }}</td>
                                <td>{{ $linha['status'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="afa-muted">Nenhum servidor conflitante encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sugestões --}}
    <div>
        <div class="afa-section-title">Sugestões alternativas e cobertura</div>
        <div class="afa-grid">
            @forelse($sugestoes as $sugestao)
                @php
                    $servidorCoberturaId = isset($sugestao['servidor_cobertura_id']) ? (int) $sugestao['servidor_cobertura_id'] : null;
                    $selecionada = $servidorCoberturaId && $servidorCoberturaId === $coberturaSelecionadaId;
                    $clicavel = $selecionarCoberturaAction && $servidorCoberturaId;
                @endphp

                @if($clicavel)
                    <button
                        type="button"
                        class="afa-card afa-choice {{ $selecionada ? 'afa-choice-selected' : '' }}"
                        wire:click="{{ $selecionarCoberturaAction }}({{ (int) $record->id }}, {{ $servidorCoberturaId }})"
                        wire:loading.attr="disabled"
                    >
                        @if($selecionada)
                            <span class="afa-bubble-mark">✓</span>
                        @endif
                        {{ $sugestao['label'] ?? '-' }}
                        <div class="afa-choice-meta">{{ $selecionada ? 'Selecionada pela IA' : 'Clique para selecionar esta cobertura' }}</div>
                    </button>
                @else
                    <div class="afa-card">{{ $sugestao['label'] ?? '-' }}</div>
                @endif
            @empty
                <div class="afa-empty">Nenhuma sugestão automática disponível.</div>
            @endforelse
        </div>
    </div>

    {{-- Coberturas disponíveis --}}
    @if(! empty($coberturas))
        <div>
            <div class="afa-section-title">Servidores IPC expediente disponíveis para cobertura</div>
            <div class="afa-grid">
                @foreach($coberturas as $id => $nome)
                    @php
                        $servidorCoberturaId = (int) $id;
                        $selecionada = $servidorCoberturaId === $coberturaSelecionadaId;
                        $clicavel = $selecionarCoberturaAction && $servidorCoberturaId;
                    @endphp

                    @if($clicavel)
                        <button
                            type="button"
                            class="afa-card afa-choice {{ $selecionada ? 'afa-choice-selected' : '' }}"
                            wire:click="{{ $selecionarCoberturaAction }}({{ (int) $record->id }}, {{ $servidorCoberturaId }})"
                            wire:loading.attr="disabled"
                        >
                            @if($selecionada)
                                <span class="afa-bubble-mark">✓</span>
                            @endif
                            {{ $nome }}
                            <div class="afa-choice-meta">{{ $selecionada ? 'Selecionada pela IA' : 'Clique para selecionar' }}</div>
                        </button>
                    @else
                        <div class="afa-card {{ $selecionada ? 'afa-choice-selected' : '' }}">
                            @if($selecionada)
                                <span class="afa-bubble-mark">✓</span>
                            @endif
                            {{ $nome }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

</div>
