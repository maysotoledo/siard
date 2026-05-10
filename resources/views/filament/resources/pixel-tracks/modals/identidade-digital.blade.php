@php
    $redes = $record->identidade_redes ?? [];
    $redesLogadas   = collect($redes)->filter(fn($r) => !empty($r['usuario']) || !empty($r['logado']))->values();
    $appsInstalados = collect($redes)->filter(fn($r) => empty($r['usuario']) && empty($r['logado']) && !empty($r['instalado']))->values();
@endphp

<div style="display:flex;flex-direction:column;gap:20px;padding:4px 0;">

    {{-- Dados pessoais (autofill) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
        <div>
            <p style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:4px;">Nome</p>
            <p style="font-size:0.95rem;font-weight:600;color:#111827;">
                {{ $record->identidade_nome ?? '—' }}
            </p>
        </div>
        <div>
            <p style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:4px;">E-mail</p>
            <p style="font-size:0.95rem;font-weight:600;">
                @if($record->identidade_email)
                    <a href="mailto:{{ $record->identidade_email }}"
                       style="color:var(--color-primary-600);text-decoration:underline;">
                        {{ $record->identidade_email }}
                    </a>
                @else
                    <span style="color:#9ca3af;">—</span>
                @endif
            </p>
        </div>
        <div>
            <p style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:4px;">Telefone</p>
            <p style="font-size:0.95rem;font-weight:600;">
                @if($record->identidade_telefone)
                    <a href="tel:{{ $record->identidade_telefone }}"
                       style="color:var(--color-primary-600);text-decoration:underline;">
                        {{ $record->identidade_telefone }}
                    </a>
                @else
                    <span style="color:#9ca3af;">—</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Contas logadas --}}
    @if($redesLogadas->isNotEmpty())
        <div>
            <p style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:8px;">
                🔐 Contas Logadas no Navegador
            </p>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($redesLogadas as $r)
                    @php
                        $icone = match(true) {
                            str_contains($r['rede'], 'Google')    => '🔵',
                            str_contains($r['rede'], 'Facebook')  => '🔵',
                            str_contains($r['rede'], 'Instagram') => '🟣',
                            str_contains($r['rede'], 'Twitter')   => '🐦',
                            str_contains($r['rede'], 'LinkedIn')  => '💼',
                            str_contains($r['rede'], 'TikTok')    => '🎵',
                            str_contains($r['rede'], 'Telegram')  => '✈️',
                            str_contains($r['rede'], 'Pinterest') => '📌',
                            default                               => '🌐',
                        };
                        $semUsername = ['Instagram','Twitter/X','LinkedIn','TikTok','Pinterest','Facebook'];
                    @endphp
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;
                                border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;">
                        <span style="font-size:1.1rem;">{{ $icone }}</span>
                        <span style="font-weight:700;color:#166534;font-size:0.9rem;">{{ $r['rede'] }}</span>

                        @if(!empty($r['usuario']))
                            <span style="color:#1e3a5f;font-weight:600;font-size:0.9rem;">
                                {{ $r['usuario'] }}
                            </span>
                            @if(!empty($r['nome']) && $r['nome'] !== $r['usuario'])
                                <span style="color:#6b7280;font-size:0.8rem;">({{ $r['nome'] }})</span>
                            @endif
                        @elseif(in_array($r['rede'], $semUsername))
                            <span style="color:#16a34a;font-style:italic;font-size:0.82rem;">✓ logado</span>
                            <span style="color:#9ca3af;font-size:0.72rem;"
                                  title="O username não é acessível por restrições do browser (SameSite cookies)">
                                · username não disponível ⓘ
                            </span>
                        @else
                            <span style="color:#16a34a;font-style:italic;font-size:0.82rem;">✓ logado</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Apps instalados (mobile) --}}
    @if($appsInstalados->isNotEmpty())
        <div>
            <p style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:8px;">
                📱 Apps Instalados (detectado no celular)
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($appsInstalados as $app)
                    <span style="display:inline-block;padding:4px 12px;border-radius:9999px;
                                 background:#e0e7ff;color:#3730a3;font-size:0.8rem;font-weight:600;">
                        {{ $app['rede'] }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if($redesLogadas->isEmpty() && $appsInstalados->isEmpty() && !$record->identidade_nome && !$record->identidade_email && !$record->identidade_telefone)
        <p style="color:#9ca3af;font-size:0.875rem;text-align:center;padding:16px 0;">
            Nenhuma informação de identidade capturada.
        </p>
    @endif
</div>
