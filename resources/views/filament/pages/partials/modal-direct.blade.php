{{-- resources/views/filament/pages/partials/modal-direct.blade.php --}}

@php
    $targetName = trim((string) ($target ?? ''));
    $otherName = trim((string) ($participant ?? ''));
    $displayName = $otherName !== '' ? $otherName : 'Direct';

    $isTarget = function (?string $author) use ($targetName): bool {
        $author = trim((string) $author);
        if ($author === '' || $targetName === '') return false;
        return strcasecmp($author, $targetName) === 0;
    };

    $messages = array_values((array) ($messages ?? []));
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-slate-50 dark:border-gray-700 dark:bg-gray-950">
    <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="min-w-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                Análise de Direct
            </div>

            <div class="mt-1 truncate text-lg font-semibold text-gray-950 dark:text-gray-100">
                {{ $displayName }}
            </div>

            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $targetName !== '' ? "Alvo: {$targetName}" : 'Log Instagram' }}
            </div>
        </div>
    </div>

    @if (count($messages) === 0)
        <div class="flex min-h-56 items-center justify-center px-4 py-10 text-sm text-gray-500 dark:text-gray-400">
            Sem mensagens encontradas.
        </div>
    @else
        <div class="max-h-[68vh] overflow-y-auto px-4 py-5">
            <div class="relative mx-auto max-w-5xl">
                <div class="absolute bottom-0 left-1/2 top-0 hidden w-px -translate-x-1/2 bg-gray-200 dark:bg-gray-700 md:block"></div>

            @foreach ($messages as $m)
                @php
                    $author = trim((string) ($m['author'] ?? ''));
                    $dt = (string) ($m['datetime'] ?? '—');
                    $body = (string) ($m['body'] ?? '—');

                    $fromTarget = $isTarget($author);
                    $label = $fromTarget ? 'Alvo' : 'Interlocutor';
                    $authorDisplay = $author !== '' && $author !== '—' ? $author : ($fromTarget ? $targetName : $displayName);
                @endphp

                    <div class="relative mb-4 grid gap-3 md:grid-cols-2">
                        <div class="{{ $fromTarget ? 'hidden md:block' : '' }}">
                            @if(! $fromTarget)
                                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $label }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $authorDisplay }}</span>
                                        <span class="ml-auto text-xs font-medium text-gray-400 dark:text-gray-500">{{ $dt }}</span>
                                    </div>

                                    <div class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-900 dark:text-gray-100">
                                        {{ $body }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="absolute left-1/2 top-5 z-10 hidden h-3 w-3 -translate-x-1/2 rounded-full border-2 border-white dark:border-gray-950 {{ $fromTarget ? 'bg-blue-500' : 'bg-gray-400' }} md:block"></div>

                        <div class="{{ $fromTarget ? '' : 'hidden md:block' }}">
                            @if($fromTarget)
                                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm dark:border-blue-500/40 dark:bg-blue-950/40">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white">
                                            {{ $label }}
                                        </span>
                                        <span class="text-xs text-blue-700 dark:text-blue-200">{{ $authorDisplay }}</span>
                                        <span class="ml-auto text-xs font-medium text-blue-500 dark:text-blue-300">{{ $dt }}</span>
                                    </div>

                                    <div class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-950 dark:text-blue-50">
                                        {{ $body }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
            @endforeach
            </div>
        </div>
    @endif
</div>
