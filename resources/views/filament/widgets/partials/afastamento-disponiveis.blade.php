@php
    /** @var \App\Enums\FuncaoOperacional|null $funcao */
    /** @var \Illuminate\Support\Collection $servidores */
@endphp

<div class="space-y-3 text-sm">
    @if (! $funcao)
        <p class="text-danger-600">Função inválida.</p>
    @else
        <div class="text-gray-600 dark:text-gray-300">
            Listagem dos servidores ativos da função
            <strong>{{ $funcao->label() }}</strong>
            que <em>não</em> estão afastados nem em cobertura na data de hoje
            ({{ now()->format('d/m/Y') }}).
        </div>

        @if ($servidores->isEmpty())
            <p class="text-gray-500 dark:text-gray-400">
                Nenhum servidor disponível no momento.
            </p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
                @foreach ($servidores as $servidor)
                    <li class="flex flex-col gap-0.5 px-3 py-2">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $servidor->name }}
                        </span>
                        @if (! empty($servidor->email))
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $servidor->email }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="text-xs text-gray-500 dark:text-gray-400">
                Total: {{ $servidores->count() }}
            </div>
        @endif
    @endif
</div>
