@php
    /** @var string $titulo */
    /** @var ?string $descricao */
    /** @var \Illuminate\Support\Collection $linhas */

    // Cores dos badges no tema claro
    $badgeColors = [
        'success' => 'background-color: rgba(34,197,94,.12); color: rgb(22,163,74);',
        'danger'  => 'background-color: rgba(239,68,68,.12); color: rgb(220,38,38);',
        'warning' => 'background-color: rgba(245,158,11,.15); color: rgb(180,83,9);',
        'info'    => 'background-color: rgba(59,130,246,.12); color: rgb(37,99,235);',
        'primary' => 'background-color: rgba(245,158,11,.15); color: rgb(180,83,9);',
        'gray'    => 'background-color: rgba(107,114,128,.15); color: rgb(75,85,99);',
    ];

    // Cores dos badges no tema escuro (maior luminosidade para manter legibilidade)
    $badgeColorsDark = [
        'success' => 'background-color: rgba(34,197,94,.15); color: rgb(134,239,172);',
        'danger'  => 'background-color: rgba(239,68,68,.15); color: rgb(252,165,165);',
        'warning' => 'background-color: rgba(245,158,11,.15); color: rgb(253,211,77);',
        'info'    => 'background-color: rgba(59,130,246,.15); color: rgb(147,197,253);',
        'primary' => 'background-color: rgba(245,158,11,.15); color: rgb(253,211,77);',
        'gray'    => 'background-color: rgba(107,114,128,.15); color: rgb(209,213,219);',
    ];
@endphp

{{-- Estilos inline para garantir dark mode independente da compilação do Tailwind --}}
<style>
    .asm-modal-wrap                    { font-size: .875rem; display: flex; flex-direction: column; gap: .75rem; }
    .asm-descricao                     { color: #4b5563; }
    .asm-vazio                         { color: #6b7280; }
    .asm-lista                         { border: 1px solid #e5e7eb; border-radius: .375rem;
                                         divide-color: #e5e7eb; list-style: none; margin: 0; padding: 0; }
    .asm-item                          { display: flex; flex-direction: column; gap: .125rem;
                                         padding: .5rem .75rem; border-bottom: 1px solid #e5e7eb; }
    .asm-item:last-child               { border-bottom: none; }
    .asm-item-header                   { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .asm-nome                          { font-weight: 500; color: #111827; }
    .asm-sub                           { font-size: .75rem; color: #6b7280; }
    .asm-badge                         { border-radius: 9999px; padding: .125rem .5rem;
                                         font-size: .75rem; font-weight: 500; white-space: nowrap; }
    .asm-total                         { font-size: .75rem; color: #6b7280; }

    /* Dark mode — usando o seletor .dark que o Filament aplica no <html> */
    .dark .asm-descricao               { color: #d1d5db; }
    .dark .asm-vazio                   { color: #9ca3af; }
    .dark .asm-lista                   { border-color: #374151; }
    .dark .asm-item                    { border-bottom-color: #374151; }
    .dark .asm-nome                    { color: #f3f4f6; }
    .dark .asm-sub                     { color: #9ca3af; }
    .dark .asm-total                   { color: #9ca3af; }
</style>

<div class="asm-modal-wrap">
    @if (! empty($descricao))
        <div class="asm-descricao">{{ $descricao }}</div>
    @endif

    @if ($linhas->isEmpty())
        <p class="asm-vazio">Nenhum registro para exibir.</p>
    @else
        <ul class="asm-lista">
            @foreach ($linhas as $linha)
                <li class="asm-item">
                    <div class="asm-item-header">
                        <span class="asm-nome">{{ $linha['nome'] ?? '-' }}</span>

                        @if (! empty($linha['badge']))
                            @php
                                $corClaro = $badgeColors[$linha['badgeColor'] ?? 'gray'] ?? $badgeColors['gray'];
                                $corEscuro = $badgeColorsDark[$linha['badgeColor'] ?? 'gray'] ?? $badgeColorsDark['gray'];
                            @endphp
                            {{-- Renderiza dois spans: um por tema, alternados via CSS --}}
                            <span class="asm-badge asm-badge-light" style="{{ $corClaro }}">
                                {{ $linha['badge'] }}
                            </span>
                            <span class="asm-badge asm-badge-dark" style="{{ $corEscuro }}">
                                {{ $linha['badge'] }}
                            </span>
                        @endif
                    </div>

                    @if (! empty($linha['sub']))
                        <span class="asm-sub">{{ $linha['sub'] }}</span>
                    @endif

                    @if (! empty($linha['meta']))
                        <span class="asm-sub">{{ $linha['meta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="asm-total">Total: {{ $linhas->count() }}</div>
    @endif
</div>

<style>
    /* Alterna badge claro/escuro conforme tema */
    .asm-badge-dark                    { display: none; }
    .dark .asm-badge-light             { display: none; }
    .dark .asm-badge-dark              { display: inline; }
</style>
