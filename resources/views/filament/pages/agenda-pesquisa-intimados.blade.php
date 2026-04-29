<x-filament-panels::page>
    <form wire:submit="search" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="search">
                <span wire:loading.remove wire:target="search">Pesquisar</span>
                <span wire:loading wire:target="search">Pesquisando...</span>
            </x-filament::button>
        </div>
    </form>

    @if ($searchedTerm !== null)
        <x-filament::section class="mt-6" heading="Resultados">
            <div class="mb-4 text-sm text-gray-600">
                Busca por: <span class="font-semibold">{{ $searchedTerm }}</span>
            </div>

            @if (count($results) === 0)
                <div class="rounded-xl border border-dashed p-6 text-sm text-gray-500">
                    Nenhum resultado encontrado.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Intimado</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Telefone</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Dia</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Horario</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Procedimento</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Escrivao</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($results as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $row['intimado'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['telefone'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['dia'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['horario'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['procedimento'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['escrivao'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
