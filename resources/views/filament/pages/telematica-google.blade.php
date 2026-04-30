<x-filament-panels::page>

<div style="display:flex; align-items:center; justify-content:flex-start; margin-bottom:22px;">
    <img
        src="{{ asset('storage/telematica/logos/google.png') }}"
        alt="Google"
        style="width:420px; max-width:100%; height:auto; object-fit:contain; object-position:left center;"
    >
</div>

    {{-- =========================
        SEGMENTO 1: DADOS FORNECIDOS
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;">
        DADOS FORNECIDOS
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <div style="display:flex; gap:24px; flex-wrap:wrap;">

            {{-- ESQUERDA: Extrajudicial + Pedido Emergencial --}}
            <div style="flex:1; min-width:320px; display:flex; flex-direction:column; gap:18px;">

                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Extrajudicial</div>
                    </div>

                    <div style="font-weight:700; margin-bottom:6px;">Dados cadastrais - Conta Google</div>
                    <div style="line-height:1.7;">
                        <div>- Informações básicas do usuário</div>
                        <div>- Telefone vinculado</div>
                        <div>- E-mail de recuperação</div>
                    </div>

                    <div style="margin-top:16px; font-weight:700; margin-bottom:6px;">Dados cadastrais - Device / Dispositivo</div>
                    <div style="line-height:1.7;">
                        <div>- Informações do dispositivo</div>
                        <div>- Contas vinculadas ao dispositivo</div>
                    </div>

                    <div style="margin-top:14px; line-height:1.7;">
                        <div>
                            <a href="{{ asset('storage/telematica/google/modelo-oficio.docx') }}"
                               download
                               style="color:#7c3aed; text-decoration:underline;">
                                📄 Modelo de ofício
                            </a>
                            <span> para requisição dos dados</span>
                        </div>

                    </div>

                    <div style="margin-top:16px; padding:12px; background:rgba(0,0,0,.06); border-radius:10px; line-height:1.6;">
                        <div style="font-weight:700; margin-bottom:8px;">*Só fornecem cadastros extrajudicialmente para os seguintes crimes:</div>
                        <div>- Art. 13-A, CPP: sequestro e cárcere privado, redução à condição análoga de escravo, tráfico de pessoas, extorsão mediante sequestro, 13: exploração sexual e envio de criança para o exterior</div>
                        <div>- Lei 12.850/13: organização criminosa</div>
                        <div>- Lei 9.613/98: crimes de "lavagem" ou ocultação de bens, direitos e valores</div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">
                            Pedido Emergencial 🚨 <span style="font-weight:400; font-size:16px;">(sem ordem judicial)</span>
                        </div>
                    </div>

                    <div style="font-style:italic; margin-bottom:10px;">
                        *Quando envolver risco de morte ou lesão grave/gravíssima iminente
                    </div>

                    <div style="font-weight:700; margin-bottom:6px;">São fornecidos:</div>
                    <div style="line-height:1.7;">
                        <div>- Dados cadastrais (conta e device)</div>
                        <div>- IP de registro e Log de acesso</div>
                        <div>- Dados Google Pay</div>
                    </div>

                    <div style="margin-top:14px; line-height:1.7;">
                        <div>▪ Efetuado na plataforma
                            <a href="https://lers.google.com/"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                LERS ↗️
                            </a>
                        </div>
                        <div>▪ Obs.: em situação emergencial procedem buscas pelo número telefônico</div>
                    </div>
                </x-filament::section>

            </div>

            {{-- DIREITA: Com Ordem Judicial --}}
            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Com Ordem Judicial</div>
                    </div>

                    <div style="line-height:1.7;">
                        <div>- Dados cadastrais</div>
                        <div>- Registros de conexão (IPs)</div>
                        <div>- Conteúdo de Gmail</div>
                        <div>- Conteúdo do Google Fotos</div>
                        <div>- Conteúdo do Google Drive</div>
                        <div>- Histórico de Pesquisa</div>
                        <div>- Histórico de Navegação</div>
                        <div>- Lista de contatos</div>
                        <div>- Backup de Apps no Google Drive (WhatsApp)</div>
                        <div>- Minha atividade no Google (Chrome, Maps, Imagens, Notícias, etc)</div>
                        <div>- Dados do Google Play (instalações e pedidos no app)</div>
                        <div>- Google Keep</div>
                        <div>- Dados do Waze</div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 2: IDENTIFICADORES
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        IDENTIFICADORES
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <x-filament::section>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="font-size:18px;">➡️</div>
                <div style="font-size:22px; font-weight:800;">Identificadores</div>
            </div>

            <div style="line-height:1.9;">
                <div>▪ <b>Conta Google (e-mail)</b></div>
                <div>▪ <b>IMEI</b> (importante calcular o dígito verificador
                    <a href="https://www.imei.info/calc/"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        ↗️
                    </a>
                    )
                </div>
                <div>▪ <b>Número de série do aparelho</b> (CSSN, fabricante e modelo do dispositivo)</div>
                <div>
                    ▪ <b>Outros</b> — Para identificadores relacionados a Youtube, Meet, IP de Mensagem enviada pelo Gmail, Blogger,
                    Google Meu Negócio, Compras no Google Play e Google Ads, consultar o documento
                    <a href="https://drive.google.com/file/d/1jvVKtSazPaYvEfkJv7Rnx7jKy7ph-2qE/view"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        ↗️
                    </a>
                    (p. 2)
                </div>
            </div>
        </x-filament::section>
    </div>


    {{-- =========================
        SEGMENTO 3: PEDIDO JUDICIAL | PLATAFORMA DE INTERAÇÃO
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        PEDIDO JUDICIAL &nbsp; | &nbsp; PLATAFORMA DE INTERAÇÃO

    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <div style="display:flex; gap:24px; flex-wrap:wrap;">

            <div style="flex:1; min-width:260px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:22px; font-weight:800;">Pedido Judicial</div>
                    </div>

                    <div style="line-height:1.7;">
                        <a href="{{ asset('storage/telematica/google/texto-tecnico-judicial.docx') }}"
                           download
                           style="color:#7c3aed; text-decoration:underline;">
                            📄 Texto técnico
                        </a>
                    </div>

                </x-filament::section>
            </div>

            <div style="flex:1; min-width:260px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:22px; font-weight:800;">Plataforma LERS</div>
                    </div>

                    <div style="line-height:1.7;">
                        <a href="https://lers.google.com/"
                           target="_blank" rel="noopener"
                           style="color:#7c3aed; text-decoration:underline;">
                            📩 envio de ofícios e decisões judiciais
                        </a>
                    </div>



                    <div style="margin-top:10px;">
                        <a href="mailto:lis-latam@google.com"
                           style="color:#2563eb; text-decoration:underline;">
                            📩 lis-latam@google.com
                        </a>
                        <div style="margin-top:6px; line-height:1.5; font-size:14px;">
                            (caso necessário falar sobre problemas em produções)
                        </div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 4: REMOÇÃO DE CONTEÚDO E RECUPERAÇÃO DE CONTA
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        REMOÇÃO DE CONTEÚDO E RECUPERAÇÃO DE CONTA
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <x-filament::section>
            <div style="line-height:2;">
                <div>▪
                    <a href="https://support.google.com/websearch/troubleshooter/3111061?hl=pt-BR"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        Orientações para remoção de conteúdo - Google ↗️
                    </a>
                </div>

                <div>▪
                    <a href="https://support.google.com/youtube/topic/6154211?hl=pt-BR"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        Orientações para remoção de conteúdo - YouTube ↗️
                    </a>
                </div>

                <div>▪
                    <a href="https://support.google.com/accounts/answer/7299973?sjid=1684865368107523196-SA"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        Processo para recuperação da Conta Google ↗️
                    </a>
                    <span> e </span>
                    <a href="https://support.google.com/accounts/answer/7682439?hl=pt-BR"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        dicas para o processo ↗️
                    </a>
                </div>
            </div>
        </x-filament::section>
    </div>

</x-filament-panels::page>
