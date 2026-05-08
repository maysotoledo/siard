@php
    $isApproved = $paymentRequest?->status === 'approved';
    $isPending = $paymentRequest?->isPending() ?? false;
    $qrCodeBase64 = $paymentRequest?->qr_code_base64;
    $qrCodeSrc = blank($qrCodeBase64)
        ? null
        : (str_starts_with($qrCodeBase64, 'data:image') ? $qrCodeBase64 : 'data:image/png;base64,' . $qrCodeBase64);
@endphp

<div
    class="space-y-6"
    wire:poll.10s="refreshPaymentStatus"
>
    <div class="rounded-2xl border border-warning-200 bg-warning-50 p-6 dark:border-warning-500/30 dark:bg-warning-500/10">
        <div class="space-y-2">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
                Pixel Tracker com liberacao mensal
            </h2>

            <p class="text-sm text-gray-700 dark:text-gray-300">
                Esta funcionalidade tem custos recorrentes com tunel, dominios e infraestrutura de rastreamento. Por isso, o uso do Pixel Tracker depende de uma mensalidade ativa.
            </p>

            <p class="text-sm text-gray-700 dark:text-gray-300">
                Assim que o Pix for confirmado pelo Mercado Pago, o sistema libera seu acesso automaticamente.
            </p>
        </div>
    </div>

    @if ($billingError)
        <div class="rounded-2xl border border-danger-200 bg-danger-50 p-5 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            {{ $billingError }}
        </div>
    @elseif ($isApproved)
        <div class="rounded-2xl border border-success-200 bg-success-50 p-5 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-200">
            Pagamento confirmado. Se a tabela ainda nao apareceu, aguarde alguns segundos ou atualize a pagina.
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1.2fr,0.8fr]">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Mensalidade</p>
                        <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $monthlyAmount }}</p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status atual</p>
                        <p class="mt-1 inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $isPending ? 'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-200' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' }}">
                            {{ $paymentRequest?->status ? str($paymentRequest->status)->replace('_', ' ')->title() : 'Aguardando geracao' }}
                        </p>
                    </div>

                    @if ($paymentRequest?->expires_at)
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Validade do Pix</p>
                            <p class="text-sm text-gray-800 dark:text-gray-200">
                                {{ $paymentRequest->expires_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    @endif

                    @if ($subscription?->expires_at)
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ultima expiracao registrada</p>
                            <p class="text-sm text-gray-800 dark:text-gray-200">
                                {{ $subscription->expires_at->format('d/m/Y') }}
                            </p>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="refreshPaymentStatus"
                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500"
                        >
                            Atualizar pagamento
                        </button>

                        <button
                            type="button"
                            wire:click="regeneratePayment"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                        >
                            Gerar novo QR Code
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">QR Code Pix</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Escaneie com o app do banco. O acesso sera liberado automaticamente apos a confirmacao.
                        </p>
                    </div>

                    @if ($qrCodeSrc)
                        <div class="flex justify-center rounded-2xl bg-white p-4">
                            <img
                                src="{{ $qrCodeSrc }}"
                                alt="QR Code Pix Mercado Pago"
                                class="h-auto w-full max-w-xs"
                            >
                        </div>
                    @endif

                    @if ($paymentRequest?->pix_copy_paste)
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pix copia e cola</p>

                            <textarea
                                readonly
                                rows="6"
                                class="w-full rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                            >{{ $paymentRequest->pix_copy_paste }}</textarea>

                            <button
                                type="button"
                                x-data="{}"
                                x-on:click="navigator.clipboard.writeText(@js($paymentRequest->pix_copy_paste))"
                                class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            >
                                Copiar codigo Pix
                            </button>
                        </div>
                    @endif

                    @if ($paymentRequest?->ticket_url)
                        <a
                            href="{{ $paymentRequest->ticket_url }}"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center justify-center rounded-xl text-sm font-medium text-primary-600 hover:text-primary-500"
                        >
                            Abrir cobranca no Mercado Pago
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
