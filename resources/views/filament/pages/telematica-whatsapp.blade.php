<x-filament-panels::page>

<div style="display:flex; align-items:center; justify-content:flex-start; margin-bottom:22px;">
    <img
        src="{{ asset('storage/telematica/logos/whatsapp.png') }}"
        alt="WhatsApp"
        style="width:360px; max-width:100%; height:auto; object-fit:contain; object-position:left center;"
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

            {{-- COLUNA ESQUERDA --}}
            <div style="flex:1; min-width:320px; display:flex; flex-direction:column; gap:18px;">

                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Extrajudicial</div>
                    </div>

                    <div style="font-weight:700; margin-bottom:8px;">Dados cadastrais*</div>

                    <div style="line-height:1.55;">
                        *Contém informações do aparelho e do sistema operacional, versão do aplicativo, data e horário do registro,
                        status da conexão, data e horário da última conexão, nome e endereço de e-mail, quando disponíveis,
                        além de informações do cliente web
                    </div>

                    <div style="margin-top:14px; line-height:1.7;">
                        {{-- ✅ Download do modelo de ofício (DOCX) --}}
                        <div>
                            <a href="{{ asset('storage/telematica/whatsapp/modelo-oficio-cadastro.docx') }}"
                               download
                               style="color:#7c3aed; text-decoration:underline;">
                                📄 Modelo de ofício
                            </a>
                            <span> para requisição dos dados</span>
                        </div>

                        {{-- ✅ Links externos (conforme você enviou) --}}
                        <div style="margin-top:6px;">
                            <a href="https://www.whatsapp.com/legal/?lang=pt_br"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                📍 Diretrizes para requisições ↗️
                            </a>
                        </div>

                        <div style="margin-top:6px;">
                            <a href="https://faq.whatsapp.com/444002211197967/"
                               target="_blank" rel="noopener"
                               style="color:#2563eb; text-decoration:underline;">
                                📍 Dados coletados pelo app ↗️
                            </a>
                        </div>

                        <div style="margin-top:12px;">
                            <b>📍 Ponto de contato com a empresa:</b>
                            Dario Campregher Neto
                            <span style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                                +55 11 93278-1979
                            </span>
                        </div>

                        <div style="margin-top:10px;">
                            <div style="font-weight:700;">WhatsApp LLC</div>
                            <div>1601 Willow Road, Menlo Park, CA 94025, United States of America</div>
                        </div>
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
                        <div>- Dados cadastrais</div>
                        <div>- Endereço de IP de criação da conta e Log de acesso</div>
                        <div>- Dos últimos 7 dias</div>
                    </div>

                    <div style="margin-top:12px;">
                        *Obs.: não deve ser anexado nenhum documento oficial (BO, ofício, relatório, etc).
                        Não é necessário preencher formulário.
                    </div>
                </x-filament::section>

            </div>

            {{-- COLUNA DIREITA --}}
            <div style="flex:1; min-width:320px; display:flex; flex-direction:column; gap:18px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:24px; font-weight:800;">Com Ordem Judicial</div>
                    </div>

                    <div style="line-height:1.7;">
                        <div>- Nome utilizado (push name) e última foto de perfil</div>
                        <div>- Logs de acesso (IPs)</div>
                        <div>- Histórico de mudança de números</div>
                        <div>- Lista de contatos (simétrica e assimétrica)</div>
                        <div>- Grupos o alvo participa e administra</div>
                        <div>- Integrantes dos grupos* (necessário oficiar ↗️ com ID dos grupos)</div>

                        <div style="margin-top:10px;">
                            - Bilhetagem (extrato dos eventos chamada e mensagens)*
                            <div style="margin-top:6px; font-size:13px; line-height:1.5;">
                                <div>*Disponível por 15 dias, mediante pedido específico</div>
                                <div style="font-style:italic;">
                                    ⚠️ Somente eventos futuros são fornecidos. O WhatsApp não armazena esse tipo de dado retroativamente
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:18px; line-height:1.55;">
                        <div style="margin-bottom:10px;">
                            Obs.: atualmente a porta lógica somente é fornecida em dois casos:
                            i) no pedido emergencial se o usuário estiver on-line no momento da coleta dos dados e
                            ii) no caso de "interceptação" dos eventos chamada e mensagens.
                        </div>

                        <div>
                            Obs.: Solicitações de registros de contas do serviço de iniciação de pagamentos pelo Facebook Pay no WhatsApp devem ser
                            endereçadas ao Facebook Pagamentos do Brasil Ltda., entidade autorizada pelo Banco Central do Brasil a iniciar transações
                            de pagamento e que processará sua solicitação
                        </div>
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>


    {{-- =========================
        SEGMENTO 2: PEDIDO JUDICIAL | PLATAFORMA DE INTERAÇÃO
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        PEDIDO JUDICIAL &nbsp; | &nbsp; PLATAFORMA DE INTERAÇÃO
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <div style="display:flex; gap:24px; flex-wrap:wrap;">

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:22px; font-weight:800;">Pedido Judicial</div>
                    </div>

                    {{-- ✅ Download do texto técnico (DOCX) --}}
                    <div style="line-height:1.7;">
                        <a href="{{ asset('storage/telematica/whatsapp/texto-tecnico-pedidos-judiciais.docx') }}"
                           download
                           style="color:#7c3aed; text-decoration:underline;">
                            📄 Texto técnico
                        </a>
                    </div>
                </x-filament::section>
            </div>

            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="font-size:18px;">➡️</div>
                        <div style="font-size:22px; font-weight:800;">Plataforma Records</div>
                    </div>

                    {{-- ✅ Link externo (conforme você enviou) --}}
                    <div style="line-height:1.7;">
                        <a href="https://www.whatsapp.com/records/login/?locale=pt_BR"
                           target="_blank" rel="noopener"
                           style="color:#2563eb; text-decoration:underline;">
                            📩 envio de ofícios e decisões judiciais
                        </a>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>


    {{-- =========================
        SEGMENTO 3: ORIENTAÇÃO GOLPES
    ========================== --}}
    <div style="background:#4b5563;color:#fff;text-align:center;font-weight:700;
                padding:12px;border-radius:12px 12px 0 0;letter-spacing:.5px;margin-top:24px;">
        ORIENTAÇÃO GOLPES
    </div>

    <div style="border:1px solid rgba(0,0,0,.12); border-top:0; border-radius:0 0 12px 12px; padding:20px;">
        <div style="display:flex; gap:24px; flex-wrap:wrap;">

            {{-- ESQUERDA --}}
            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">🧾</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Providências em caso de "roubo de conta"
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # usuário perdeu acesso a sua conta de WhatsApp
                            </div>
                        </div>
                    </div>

                    <div style="line-height:1.8; margin-top:14px;">
                        <div><b>1</b> - Acesse o site
                            <a href="https://www.whatsapp.com/contact/noclient"
                               target="_blank" rel="noopener"
                               style="color:#2563eb;text-decoration:underline;">
                                www.whatsapp.com/contact/noclient
                            </a>
                        </div>
                        <div><b>2</b> - Preencha com os dados da conta</div>
                        <div><b>3</b> - No campo "Insira sua mensagem" inclua o texto abaixo:</div>

                        <div style="margin-top:12px;">
                            <div><i>Suporte do WhatsApp,</i></div>
                            <br>
                            <div>
                                <i>Minha conta de WhatsApp foi roubada/sequestrada e atualmente está sendo usada sem a minha autorização.
                                Abaixo estão os detalhes:</i>
                            </div>

                            <div style="margin-top:10px;">
                                • <i>Número de telefone: coloque +55 antes do número</i><br>
                                • <i>Nome completo:</i><br>
                                • <i>Descrição do problema: Minha conta de WhatsApp foi acessada por terceiros não autorizados que estão mandando mensagens sem o meu consentimento.</i>
                            </div>

                            <div style="margin-top:10px;">
                                <i>Peço que bloqueiem temporariamente a minha conta para impedir o uso indevido e me orientem sobre o processo para recuperá-la com segurança.</i><br>
                                <i>Estou disponível para fornecer qualquer informação adicional.</i>
                            </div>

                            <div style="margin-top:10px;">
                                <i>Atenciosamente,</i><br>
                                <i>[Seu E-mail de Contato]</i>
                            </div>
                        </div>

                        <div style="margin-top:14px;">
                            <b>4</b> - Mande o mesmo texto para o e-mail <b>support@whatsapp.com</b> - Assunto: <b>Urgente - WhatsApp Roubado</b>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- DIREITA --}}
            <div style="flex:1; min-width:320px;">
                <x-filament::section>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:6px;">
                        <div style="font-size:18px;">🧾</div>
                        <div>
                            <div style="font-size:18px; font-weight:800;">
                                Providências em caso de perfil falso - conta impostora
                            </div>
                            <div style="font-style:italic; opacity:.85;">
                                # golpista se passando por pessoa alheia no WhatsApp
                            </div>
                        </div>
                    </div>

                    <div style="line-height:1.8; margin-top:14px;">
                        <div><b>1.</b> Avise seus contatos próximos informando sobre o ocorrido, solicitando que não sejam realizadas transferências bancárias. (Informe também em suas redes sociais)</div>
                        <div><b>2.</b> Solicite que denunciem o número que está se passando por você</div>
                        <div><b>3.</b> Obtenha print da conversa de pessoas que receberam mensagem do golpista</div>
                        <div><b>4.</b> Informe o WhatsApp sobre o golpe através do e-mail <b>support@whatsapp.com</b></div>

                        <div style="margin-top:10px;">
                            ▪ insira o telefone do golpista com o código do país e DDD (ex. +55 48 9xxxxxx)<br>
                            ▪ anexe os prints da conversa aparecendo o número do golpista e o pedido de transferência de valores (muito importante para ajudar a empresa WhatsApp a tomar a decisão mais rápido)
                        </div>

                        <div style="margin-top:12px;">
                            <i>Prezados,</i><br>
                            <i>O número +55 47 XXXXX-XXXX está utilizando indevidamente minha foto e nome no WhatsApp para pedir dinheiro aos meus contatos, caracterizando golpe e violando os termos de uso do aplicativo.</i><br>
                            <i>Solicito a desativação imediata dessa conta.</i><br>
                            <i>Permaneco à disposição para eventuais esclarecimentos.</i><br><br>
                            <i>Atenciosamente,</i><br>
                            <i>[Seu nome completo]</i><br>
                            <i>[Seu telefone verdadeiro]</i>
                        </div>
                    </div>
                </x-filament::section>
            </div>

        </div>
    </div>

</x-filament-panels::page>
