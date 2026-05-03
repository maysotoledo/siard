<div class="space-y-6">
    <div>
        <div class="text-sm font-semibold text-gray-700">Função operacional</div>
        <div class="mt-2 rounded-lg border p-3 text-sm">
            <div>Servidor: {{ $record->user?->name ?? '-' }}</div>
            <div>Função: {{ $record->user?->funcao_operacional?->label() ?? '-' }}</div>
            <div>Grupo: {{ $record->user?->funcao_operacional?->grupoOperacional() === 'plantao' ? 'Plantão' : 'Expediente' }}</div>
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
