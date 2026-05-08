@php
    $isApproved = $paymentRequest?->status === 'approved';
    $isPending = $paymentRequest?->isPending() ?? false;
    $hasPaymentRequest = $paymentRequest !== null;
    $featureName = $featureName ?? 'IP Grabber';
    $qrCodeBase64 = $paymentRequest?->qr_code_base64;
    $qrCodeSrc = blank($qrCodeBase64)
        ? null
        : (str_starts_with($qrCodeBase64, 'data:image') ? $qrCodeBase64 : 'data:image/png;base64,' . $qrCodeBase64);
@endphp

<div class="space-y-6" @if ($hasPaymentRequest) wire:poll.10s="refreshPaymentStatus" @endif>
    <div class="rounded-2xl border border-warning-200 bg-warning-50 p-6 dark:border-warning-500/30 dark:bg-warning-500/10">
        <div class="space-y-2">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
                {{ $featureName }} com liberacao mensal
            </h2>

            <p class="text-sm text-gray-700 dark:text-gray-300">
                Esta funcionalidade tem custos recorrentes com tunel, dominios e infraestrutura de rastreamento. Por isso, o uso do {{ $featureName }} depende de uma mensalidade ativa.
            </p>

            <p class="text-sm text-gray-700 dark:text-gray-300">
                A cobrança é única. Assim que o Pix for confirmado pelo Mercado Pago, o sistema libera automaticamente o acesso ao IP Grabber e ao Tracker de E-mail.
            </p>
        </div>
    </div>

    @if ($isApproved)
        <div class="rounded-2xl border border-success-200 bg-success-50 p-5 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-200">
            Pagamento confirmado. Se a tabela ainda nao apareceu, aguarde alguns segundos ou atualize a pagina.
        </div>
    @else
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Mensalidade</p>
                    <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $monthlyAmount }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status atual</p>
                    <p class="mt-1 inline-flex rounded-full border px-3 py-1 text-sm font-semibold {{ $isPending ? 'border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-400/70 dark:bg-amber-400/20 dark:text-amber-100' : 'border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-500/70 dark:bg-slate-400/15 dark:text-slate-100' }}">
                        {{ $paymentRequest?->status ? str($paymentRequest->status)->replace('_', ' ')->title() : 'Aguardando geracao' }}
                    </p>
                </div>

                @if (! $hasPaymentRequest)
                    <div class="rounded-2xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-400/70 dark:bg-sky-400/20 dark:text-sky-100">
                        Clique em <strong>Pagar</strong> para gerar o QR Code Pix. A cobranca fica disponivel por 10 minutos.
                    </div>
                @endif

                @if ($billingError)
                    <div class="rounded-2xl border border-danger-200 bg-danger-50 p-5 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/15 dark:text-danger-100">
                        {{ $billingError }}
                    </div>
                @elseif ($qrCodeSrc)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900 dark:border-emerald-400/40 dark:bg-emerald-400/10 dark:text-emerald-100">
                        O QR Code foi gerado com sucesso.
                    </div>
                @elseif ($paymentRequest?->ticket_url)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-100">
                        A cobranca foi criada, mas o QR Code ainda nao foi retornado pelo Mercado Pago neste carregamento.
                    </div>
                @endif

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
                    @if (! $hasPaymentRequest)
                        <button
                            type="button"
                            wire:click.prevent="startPayment"
                            wire:loading.attr="disabled"
                            wire:target="startPayment"
                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="startPayment">Pagar</span>
                            <span wire:loading wire:target="startPayment">Gerando...</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click.prevent="refreshPaymentStatus"
                            wire:loading.attr="disabled"
                            wire:target="refreshPaymentStatus"
                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="refreshPaymentStatus">Atualizar pagamento</span>
                            <span wire:loading wire:target="refreshPaymentStatus">Atualizando...</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click.prevent="regeneratePayment"
                        wire:loading.attr="disabled"
                        wire:target="regeneratePayment"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-70 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <span wire:loading.remove wire:target="regeneratePayment">
                            {{ $hasPaymentRequest ? 'Gerar novo QR Code' : 'Regerar cobranca' }}
                        </span>
                        <span wire:loading wire:target="regeneratePayment">Gerando...</span>
                    </button>

                    @if ($qrCodeSrc)
                        <button
                            type="button"
                            wire:click="openQrModal"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500"
                        >
                            Abrir QR Code
                        </button>
                    @endif
                </div>

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
    @endif

    {{-- Modal QR Code: x-data sempre presente no DOM; <template x-teleport> move o overlay direto para o <body> --}}
    <div
        x-data="{
            open: @js(($shouldOpenQrModal ?? false) && !blank($qrCodeSrc ?? '')),
            qrSrc: @js($qrCodeSrc ?? ''),
            pixCode: @js($paymentRequest?->pix_copy_paste ?? ''),
        }"
        x-on:pixel-open-qr-modal.window="
            qrSrc = $event.detail.qrCodeSrc;
            pixCode = $event.detail.pixCopyPaste;
            open = true;
        "
    >
        <template x-teleport="body">
            <div
                x-show="open && qrSrc"
                style="position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.72); padding:16px;"
            >
                <div style="width:100%; max-width:420px; border-radius:24px; background:#ffffff; padding:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,.45);">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
                        <div>
                            <h3 style="margin:0; font-size:18px; font-weight:600; color:#111827;">QR Code Pix</h3>
                            <p style="margin:6px 0 0; font-size:14px; color:#4b5563;">
                                Escaneie para concluir o pagamento da mensalidade.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="closeQrModal"
                            x-on:click="open = false"
                            style="border:none; background:transparent; color:#6b7280; font-size:16px; cursor:pointer;"
                        >
                            X
                        </button>
                    </div>

                    <div style="margin-top:24px; display:flex; justify-content:center; align-items:center; border-radius:16px; background:#f9fafb; padding:16px;">
                        <img
                            :src="qrSrc"
                            alt="QR Code Pix Mercado Pago"
                            style="display:block; margin:0 auto; width:260px; height:260px; max-width:100%; object-fit:contain;"
                        >
                    </div>

                    <div x-show="pixCode" style="margin-top:16px;">
                        <p style="margin:0 0 8px; font-size:14px; font-weight:500; color:#4b5563;">Pix copia e cola</p>
                        <textarea
                            readonly
                            rows="5"
                            x-bind:value="pixCode"
                            style="width:100%; border:1px solid #d1d5db; border-radius:12px; background:#f9fafb; padding:10px 12px; font-size:12px; color:#374151;"
                        ></textarea>

                        <button
                            type="button"
                            x-on:click="navigator.clipboard.writeText(pixCode)"
                            class="mt-3 inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Copiar codigo Pix
                        </button>
                    </div>

                    <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                        <button
                            type="button"
                            wire:click="closeQrModal"
                            x-on:click="open = false"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
