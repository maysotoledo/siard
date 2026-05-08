<x-filament-panels::page>
    <x-filament::section heading="Resumo do pixel">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @foreach ($acessos as $acesso)
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
                                        <a href="{{ $acesso['gps_url'] }}" target="_blank" rel="noopener noreferrer"
                                           style="display:inline-flex;align-items:center;gap:4px;text-decoration:underline;color:var(--color-primary-600);">
                                            {{ $acesso['gps'] }}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="flex-shrink:0;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    @else
                                        {{ $acesso['gps'] }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['gps_accuracy'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['isp'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['idioma'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['plataforma'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $acesso['resolucao'] }}</td>
                                <td class="max-w-xs truncate px-3 py-2 text-gray-700 dark:text-gray-300" title="{{ $acesso['referer'] }}">{{ $acesso['referer'] }}</td>
                                <td class="max-w-md truncate px-3 py-2 text-gray-700 dark:text-gray-300" title="{{ $acesso['user_agent'] }}">{{ $acesso['user_agent'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
