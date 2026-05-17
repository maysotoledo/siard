<div class="space-y-5">
    <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-800">
        <div class="font-semibold text-gray-950 dark:text-white">
            Contexto #{{ $context->id }}
        </div>
        <div class="mt-2 grid gap-2 text-gray-600 dark:text-gray-300 sm:grid-cols-2">
            <div><strong>Criado por:</strong> {{ $context->user?->name ?? 'Nao informado' }}</div>
            <div><strong>Email:</strong> {{ $context->user?->email ?? 'Nao informado' }}</div>
            <div><strong>Investigacao:</strong> {{ $context->analiseInvestigation?->name ?? 'Nao vinculada' }}</div>
            <div><strong>Arquivo:</strong> {{ $context->arquivo_original ?: 'Sem arquivo' }}</div>
        </div>
    </div>

    @if ($context->aiReports->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Nenhum relatório IA foi criado para este contexto.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Criado por</th>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">Criado em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($context->aiReports->sortByDesc('created_at') as $report)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                {{ str($report->tipo)->replace('_', ' ')->title() }}
                            </td>
                            <td class="px-4 py-3">{{ $report->status }}</td>
                            <td class="px-4 py-3">
                                {{ $report->user?->name ?? 'Nao informado' }}
                                <div class="text-xs text-gray-500">{{ $report->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $report->model ?: $report->provider ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $report->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                        </tr>
                        @if ($report->resposta)
                            <tr>
                                <td colspan="5" class="bg-gray-50 px-4 py-3 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <div class="max-h-52 overflow-auto whitespace-pre-wrap">{{ $report->resposta }}</div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
