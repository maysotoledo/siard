<x-filament-panels::page>

<div style="display:flex; align-items:center; justify-content:flex-start; margin-bottom:22px;">
    <img
        src="{{ asset('storage/telematica/logos/meta.png') }}"
        alt="Meta"
        style="width:430px; max-width:100%; height:auto; object-fit:contain; object-position:left center;"
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

                {{-- Card: Extrajudicial --}}
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Extrajudicial</div>
                    </div>

                    <div style="font-weight:700; margin-bottom:6px;">Dados cadastrais</div>
                    <div style="line-height:1.7;">
                        <div>- Informações básicas do usuário</div>
                        <div>- IP de criação da conta</div>
                    </div>

                    <div style="margin-top:14px; line-height:1.7;">
                        <div>
                            <a href="{{ asset('storage/telematica/meta/modelo-oficio-cadastros-id-ou-perfil.docx') }}"
                               download
                               style="color:#7c3aed; text-decoration:underline;">
                                📄 Modelo de ofício
                            </a>
                            <span> para requisição dos dados</span>
                        </div>

                        <div style="margin-top:10px;">
                            <a href="https://help.instagram.com/155833707900388"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                📍 Diretrizes para requisições ↗️
                            </a>
                        </div>

                        {{-- você não passou o link do Instagram; deixei como placeholder --}}
                        {{-- <div style="margin-top:6px;">
                            <span style="opacity:.85;">📍 Diretrizes para requisições Instagram ↗️</span>
                            <span style="opacity:.75;">(adicione o link quando tiver)</span>
                        </div> --}}
                    </div>
                </x-filament::section>

                {{-- ✅ NOVO Card: Pedido Emergencial --}}
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
                        <div>- Dados cadastrais</div>
                        <div>- IP de registro e Log de acesso</div>
                        <div>- Geolocalização (somente o GPS estiver ativo)</div>
                        <div>- Dos últimos 7 dias</div>
                    </div>

                    <div style="margin-top:12px;">
                        *Obs.: não deve ser anexado nenhum documento oficial (BO, ofício, relatório, etc).
                        Não é necessário preencher formulário.
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
                        <div>- Logs de acesso (IPs)</div>
                        <div>- Devices (dispositivos vinculados à conta)</div>
                        <div>- Seguidores/Seguindo</div>
                        <div>- Curtidas e atividade</div>
                        <div>- Conversas existentes na conta no momento da produção</div>
                        <div>- Machines cookies / mobile devices (contas logadas a partir do mesmo terminal)</div>
                        <div>- Dado de geolocalização (última localização)</div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 2: IDENTIFICADORES (TUDO NA MESMA DIV/SECTION)
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

            <div style="line-height:1.9; margin-bottom:16px;">
                <div>
                    🔎
                    <a href="https://lookup-id.com/"
                       target="_blank" rel="noopener"
                       style="color:#7c3aed; text-decoration:underline;">
                        🔗 Identificar a ID do usuário [FB] ↗️
                    </a>
                </div>

                <div>
                    🔎
                    <a href="https://commentpicker.com/instagram-user-id.php"
                       target="_blank" rel="noopener"
                       style="color:#7c3aed; text-decoration:underline;">
                        🔗 Identificar a ID do usuário [Instagram] ↗️
                    </a>
                </div>
            </div>

            <div style="line-height:1.8;">
                <div>▪ <b>Nome de usuário (vanity name)</b> — Ex.: "PCMTOficial" do www.facebook.com/PCMTOficial. Ou o @ do usuário no Instagram.</div>
                <div>▪ <b>UID</b></div>
                <div>▪ <b>E-mail</b></div>
                <div>▪ <b>Telefone</b> (Exemplo: +5566997880000)</div>
            </div>

            <div style="margin-top:18px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <div style="font-size:18px;">➡️</div>
                    <div style="font-size:16px; font-weight:800;">
                        Quando não houver identificador válido e for necessário encaminhar o ofício ou a ordem judicial
                    </div>
                </div>

                <div style="line-height:1.8;">
                    <div>▪ Quando todos os targets do pedido retornarem negativo na tentativa de associação no portal, poderá ser utilizado um identificador genérico (placeholder) para cumprimento do requisito. O identificador é <b>499525998</b>, que deverá ser inserido como alvo de conta do Facebook, mesmo que o pedido seja referente ao Instagram.</div>
                    <div style="margin-top:8px;">▪ No campo Informação adicional, informe a conta da qual se busca dados e registre que não foi possível adicioná-la no portal. Preencha os demais campos conforme o documento anexado.</div>
                    <div style="margin-top:8px;">▪ Esse procedimento deve ser utilizado somente quando todos os alvos retornarem negativo. Caso ao menos um alvo seja aceito no portal, não utilize o código placeholder. Nessa situação, registre apenas no campo Informação adicional que a conta foi tentada, mas não pôde ser adicionada, solicitando o processamento por expresso.</div>
                </div>
            </div>
        </x-filament::section>
    </div>


    {{-- =========================
        SEGMENTO 3: PEDIDO JUDICIAL | PLATAFORMA DE INTERAÇÃO
        (3 colunas: Pedido Judicial / Records / E-mail)
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
                        <a href="{{ asset('storage/telematica/meta/pedido-judicial.docx') }}"
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
                        <div style="font-size:22px; font-weight:800;">Plataforma Records</div>
                    </div>

                    <div style="line-height:1.7;">
                        <a href="https://www.facebook.com/records/login/"
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

                    <div style="line-height:1.7;">
                        <a href="mailto:records@facebook.com"
                           style="color:#2563eb; text-decoration:underline;">
                            📩 records@facebook.com
                        </a>
                    </div>

                    <div style="margin-top:10px; line-height:1.5; font-size:14px;">
                        solicitações que não podem ser feitas pelo portal, p. ex., remoção de conteúdo
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 4: ORIENTAÇÃO GOLPES
        (4 blocos)
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        ORIENTAÇÃO GOLPES
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <div style="display:flex; gap:24px; flex-wrap:wrap;">

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">📱</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Recuperação de conta invadida - pelo usuário
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # usuário perdeu acesso da sua conta
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; line-height:1.9;">
                        <div>▪ Instagram - processo pelo usuário
                            <a href="https://help.instagram.com/149494825257596?helpref=uf_permalink"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui)
                            </a>
                        </div>

                        <div>▪ Facebook - processo pelo usuário
                            <a href="https://www.facebook.com/hacked"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui)
                            </a>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">🧾</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Solicitação de remoção de conteúdo pela vítima
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # violação de privacidade
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; line-height:1.9;">
                        <div>▪ Instagram
                            <a href="https://help.instagram.com/122717417885747"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>

                        <div>▪ Instagram - perfil falso
                            <a href="https://help.instagram.com/contact/636276399721841"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>

                        <div>▪ Facebook
                            <a href="https://www.facebook.com/help/contact/516343134409068"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">🛡️</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Recuperação de conta invadida - pela autoridade
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # ofício enviado pela plataforma Records
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; line-height:1.9;">
                        <div>▪ Acessar a plataforma Records
                            <a href="https://www.facebook.com/records/login/"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>

                        <div style="margin-top:10px;">
                            <a href="{{ asset('storage/telematica/meta/modelo-recuperacao-conta-invadida.docx') }}"
                               download
                               style="color:#7c3aed; text-decoration:underline;">
                                📄 Modelo para envio de solicitação (DOCX)
                            </a>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">🔎</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Verificar histórico de atividades e baixar informações da conta
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # usuário acessa e baixa seu conteúdo
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; line-height:1.9;">
                        <div>▪ Instagram
                            <a href="https://www.instagram.com/download/request/"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>

                        <div>▪ Facebook
                            <a href="https://www.facebook.com/login.php?next=https%3A%2F%2Fwww.facebook.com%2Fdyi%2F"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                (aqui ↗️)
                            </a>
                        </div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>

</x-filament-panels::page>
