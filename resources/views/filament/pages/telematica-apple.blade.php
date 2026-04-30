<x-filament-panels::page>

<div style="display:flex; align-items:center; justify-content:flex-start; margin-bottom:22px;">
    <img
        src="{{ asset('storage/telematica/logos/apple.png') }}"
        alt="Apple"
        style="width:240px; max-width:100%; height:auto; object-fit:contain; object-position:left center;"
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

                {{-- Card Extrajudicial --}}
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Extrajudicial</div>
                    </div>

                    <div style="font-weight:700; margin-bottom:6px;">Dados cadastrais - Conta Apple</div>
                    <div style="line-height:1.7;">
                        <div>- Informações básicas do usuário</div>
                        <div>- Telefone e e-mail associados</div>
                        <div>- DSID</div>
                    </div>

                    <div style="margin-top:16px; font-weight:700; margin-bottom:6px;">Dados cadastrais - Device / Dispositivo</div>
                    <div style="line-height:1.7;">
                        <div>- Informações do dispositivo</div>
                        <div>- Conta Apple associada (DSID)</div>
                    </div>

                    <div style="margin-top:14px; line-height:1.7;">
                        {{-- Se você tiver o modelo de ofício Apple (docx/pdf), coloque aqui e aponte com asset('storage/...') --}}
                        <div style="opacity:.8;">
                           <a href="{{ asset('storage/telematica/apple/apple-modelo-oficio.docx') }}"
                               download
                               style="color:#7c3aed; text-decoration:underline;">
                                📄 Modelo de ofício
                            </a>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Card Pedido Emergencial --}}
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">
                            Pedido Emergencial 🚨 <span style="font-weight:400; font-size:16px;">(sem ordem judicial)</span>
                        </div>
                    </div>

                    <div style="font-style:italic; margin-bottom:12px; line-height:1.7;">
                        *A Apple considera que uma solicitação é emergencial quando está relacionada a uma ou mais circunstâncias
                        que envolvam uma ou mais ameaças graves e iminentes:
                        <div style="margin-top:10px;">
                            1) à vida ou segurança das pessoas;<br>
                            2) à segurança de um Estado;<br>
                            3) à segurança de infraestruturas ou instalações críticas.
                        </div>
                    </div>

                    <div style="line-height:1.9;">
                        <div>▪ Enviar pela plataforma
                            <a href="https://lep.apple.com/#/"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                LEP ↗️
                            </a>
                        </div>

                        <div style="margin-top:8px;">
                            ▪ Se for enviado por e-mail <b>exigent@apple.com</b>, preencher o
                            <a href="https://www.apple.com/legal/privacy/le-emergencyrequest-br.pdf"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                formulário de requisição ↗️
                            </a>
                            e indicar os dados que se pretende obter.
                        </div>
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
                        <div>▪ Backup do dispositivo iOS</div>
                        <div>▪ Caixa de e-mails (enviados, recebidos, etc) - iCloud Mail</div>
                        <div>▪ Agenda de contatos</div>
                        <div>▪ Fotos, vídeos, documentos</div>
                        <div>▪ iCloud Drive (dados armazenados em nuvem)</div>
                        <div>▪ Histórico de localização</div>
                        <div>▪ Atividade da carteira (Apple Pay)</div>
                        <div>▪ Notas, Lembretes, Pages, Keynotes</div>
                    </div>

                    <div style="margin-top:14px; padding:12px; background:rgba(0,0,0,.06); border-radius:10px; line-height:1.6;">
                        Obs.: para pedidos judiciais, recomenda-se utilizar como identificador a Conta Apple ou o DSID,
                        que podem ser obtidos por meio de requisição cadastral utilizando outros identificadores disponíveis.
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
                <div>▪ <b>Conta Apple</b> — para verificar se um e-mail é Conta Apple
                    <a href="https://iforgot.apple.com/password/verify/appleid"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        (aqui)
                    </a>
                </div>

                <div>▪ <b>IMEI</b> (importante calcular o dígito verificador
                    <a href="https://www.imei.info/calc"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        ↗️
                    </a>
                    )
                </div>

                <div>▪ <b>Número de série do dispositivo</b></div>
                <div>▪ <b>DSID</b></div>
                <div>▪ <b>MAC Address</b></div>
            </div>
        </x-filament::section>
    </div>


    {{-- =========================
        SEGMENTO 3: PEDIDO JUDICIAL | PLATAFORMA DE INTERAÇÃO
        (3 colunas: Pedido Judicial / Plataforma LEP / E-mail)
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

                    {{-- Se você tiver o Texto técnico Apple (docx/pdf), coloque aqui --}}
                    <div style="opacity:.8; line-height:1.7;">
                       <a href="{{ asset('storage/telematica/apple/apple-tecnico-judicial.docx') }}"
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
                        <div style="font-size:22px; font-weight:800;">Plataforma LEP</div>
                    </div>

                    <div style="line-height:1.7;">
                        <a href="https://lep.apple.com/#/"
                           target="_blank" rel="noopener"
                           style="color:#7c3aed; text-decoration:underline;">
                            📩 envio de ofícios e decisões judiciais
                        </a>
                    </div>
                </x-filament::section>
            </div>

            <div style="flex:1; min-width:260px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:22px; font-weight:800;">E-mail</div>
                    </div>

                    <div style="line-height:1.8;">
                        <div>📩 <a href="mailto:lawenforcement@apple.com" style="color:#2563eb; text-decoration:underline;">lawenforcement@apple.com</a></div>
                        <div>📩 Emergencial: <a href="mailto:exigent@apple.com" style="color:#2563eb; text-decoration:underline;">exigent@apple.com</a></div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 4: DOWNLOAD DOS DADOS E ANÁLISE
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        DOWNLOAD DOS DADOS E ANÁLISE
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <x-filament::section>
            <div style="line-height:2;">
                <div>▪ <span style="opacity:.85;">Visão geral do processo ↗️</span> <span style="opacity:.7;">(adicione o link se desejar)</span></div>

                <div>
                    ▪ Download e descriptografia da produção — <i>MAGNET Apple Warrant Return Assistant</i> que automatiza o processo:
                    <a href="https://www.magnetforensics.com/resources/magnet-apple-warrant-return-assistant/"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        site da ferramenta ↗️
                    </a>
                    /
                    <a href="https://drive.google.com/file/d/1Jf-9eAog7mfmBVab6rBXK5I8LpA-DJ3c/view?usp=drive_link"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        arquivo ↗️
                    </a>
                </div>

                <div>
                    ▪ Processamento dos dados. Recomenda-se realizar a decodificação dos dados fornecidos pela Apple na ferramenta
                    <i>Physical Analyzer</i> — Cellebrite
 (
                     <a href="https://drive.google.com/file/d/1MMf0vsnCN8-7XcHHKMKt_X8xNVhGvrVI/view?usp=drive_link"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        tutorial ↗️
                    </a>
                    /
                    <a href="https://drive.google.com/file/d/1Fzx6HsYPleKVLGhgjpDNQ9VeK9tPBsM3/view"
                       target="_blank" rel="noopener"
                       style="color:#2563eb; text-decoration:underline;">
                        vídeo ↗️
                    </a>
                    ), que facilita a análise do conteúdo.

                </div>
            </div>
        </x-filament::section>
    </div>

</x-filament-panels::page>
