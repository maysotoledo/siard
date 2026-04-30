<x-filament-panels::page>
    @php
        $img = fn (string $file) => asset("storage/telematica/avilla/{$file}");
    @endphp

    <style>
        .avilla-article-wrap {
            background: #fff;
            border-radius: 28px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.06);
        }

        .avilla-article-hero {
            background:
                radial-gradient(circle at top left, rgba(6, 182, 212, 0.22), transparent 38%),
                linear-gradient(180deg, #06263f 0%, #081b2c 100%);
            padding: 22px 22px 0;
        }

        .avilla-article-hero img {
            width: 100%;
            display: block;
            border-radius: 24px 24px 0 0;
            object-fit: cover;
            max-height: 420px;
        }

        .avilla-article {
            max-width: 860px;
            margin: 0 auto;
            padding: 30px 24px 42px;
            color: #0f172a;
        }

        .avilla-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        .avilla-page-title {
            font-size: clamp(2rem, 3vw, 2.8rem);
            line-height: 1.08;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin: 0 0 12px;
        }

        .avilla-page-subtitle {
            font-size: 1.18rem;
            line-height: 1.55;
            font-weight: 800;
            margin: 0 0 26px;
            color: #0f172a;
        }

        .avilla-article h2 {
            font-size: 1.65rem;
            line-height: 1.2;
            font-weight: 900;
            margin: 34px 0 14px;
            letter-spacing: -0.02em;
        }

        .avilla-article h3 {
            font-size: 1.15rem;
            line-height: 1.3;
            font-weight: 900;
            margin: 24px 0 10px;
        }

        .avilla-article p,
        .avilla-article li {
            font-size: 1rem;
            line-height: 1.9;
            color: #334155;
        }

        .avilla-article p {
            margin: 0 0 16px;
        }

        .avilla-article ul,
        .avilla-article ol {
            margin: 0 0 18px 18px;
            padding-left: 18px;
        }

        .avilla-inline-note {
            display: block;
            margin: 14px 0 18px;
            padding: 16px 18px;
            border-left: 4px solid #0ea5e9;
            border-radius: 14px;
            background: #f0f9ff;
            color: #0f172a;
            font-weight: 600;
            line-height: 1.8;
        }

        .avilla-figure {
            margin: 22px 0 26px;
            text-align: center;
        }

        .avilla-figure img {
            max-width: 100%;
            height: auto;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .avilla-figure figcaption {
            margin-top: 10px;
            font-size: 0.92rem;
            color: #64748b;
            line-height: 1.7;
        }

        .avilla-resource-box {
            margin-top: 28px;
            padding: 18px 20px;
            border-radius: 18px;
            border: 1px solid rgba(14, 165, 233, 0.16);
            background: linear-gradient(180deg, #f8fdff 0%, #eef8ff 100%);
        }

        .avilla-resource-box a,
        .avilla-inline-note a {
            color: #0369a1;
            font-weight: 800;
            text-decoration: underline;
        }

        html.dark .avilla-article-wrap {
            background: #0f172a;
            border-color: rgba(148, 163, 184, 0.16);
            box-shadow: none;
        }

        html.dark .avilla-article {
            color: #e5eef8;
        }

        html.dark .avilla-page-subtitle,
        html.dark .avilla-article h2,
        html.dark .avilla-article h3 {
            color: #f8fafc;
        }

        html.dark .avilla-meta,
        html.dark .avilla-figure figcaption {
            color: #94a3b8;
        }

        html.dark .avilla-article p,
        html.dark .avilla-article li {
            color: #cbd5e1;
        }

        html.dark .avilla-inline-note {
            background: rgba(8, 47, 73, 0.58);
            color: #e0f2fe;
            border-left-color: #38bdf8;
        }

        html.dark .avilla-figure img {
            border-color: rgba(148, 163, 184, 0.16);
        }

        html.dark .avilla-resource-box {
            background: linear-gradient(180deg, rgba(8, 47, 73, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%);
            border-color: rgba(56, 189, 248, 0.2);
        }

        html.dark .avilla-resource-box a,
        html.dark .avilla-inline-note a {
            color: #7dd3fc;
        }

        @media (max-width: 768px) {
            .avilla-article {
                padding: 24px 18px 34px;
            }
        }
    </style>

    <div class="avilla-article-wrap">
        <div class="avilla-article-hero">
            <img src="{{ $img('page-01-image-01.png') }}" alt="Capa Avilla Forensics">
        </div>

        <article class="avilla-article">
 

            <h1 class="avilla-page-title">Avilla Forensics: Ferramenta Gratuita de Análise de Smartphones</h1>
            <p class="avilla-page-subtitle">Avilla Forensics: ferramenta gratuita para coleta e análise de smartphones.</p>

            <h2>Conceitos iniciais de Forense Digital</h2>
            <p>
                Antes de entrar na ferramenta, o material contextualiza a forense digital como um ramo aplicado da ciência forense,
                voltado à análise de vestígios cibernéticos e demais elementos digitais relevantes para apuração de fatos com interesse jurídico.
            </p>
            <p>
                Em termos práticos, evidência digital é qualquer dado armazenado ou transmitido em formato binário que possa demonstrar
                uma ação, estabelecer vínculo entre pessoas ou ajudar a reconstruir eventos em investigações criminais ou cíveis.
            </p>
            <p>
                Isso inclui desde computadores e mídias removíveis até smartphones, que concentram boa parte da rotina moderna de comunicação,
                localização, fotografias, arquivos e registros de aplicativos.
            </p>

            <span class="avilla-inline-note">
                Você pode gostar de assistir:
                <a href="https://youtu.be/EHUOKyc3DsQ" target="_blank" rel="noopener">Webinar Introdução à Coleta Forense de Evidências Digitais</a>.
            </span>

            <h2>Novos paradigmas e a proposta do Avilla</h2>
            <p>
                O texto destaca a mobilidade como um dos grandes paradigmas tecnológicos recentes: smartphones e tablets concentram
                volumes cada vez maiores de informações pessoais e profissionais, o que amplia o interesse pericial nesse ecossistema.
            </p>
            <p>
                Nesse contexto, o <strong>Avilla Forensics</strong> aparece como uma ferramenta gratuita de coleta e análise voltada a
                dispositivos móveis, com operação baseada em <strong>ADB</strong>, criação de backups, instalação de agente próprio e
                apoio a rotinas complementares de análise.
            </p>

            <div class="avilla-resource-box">
                <strong>Dependências e ferramentas citadas no material:</strong>
                <ul style="margin-top: 12px;">
                    <li><a href="https://www.java.com/pt-BR/download/" target="_blank" rel="noopener">Java</a></li>
                    <li><a href="https://www.python.org/" target="_blank" rel="noopener">Python</a></li>
                    <li><a href="https://notepad-plus-plus.org/downloads/" target="_blank" rel="noopener">Notepad++</a></li>
                    <li><a href="https://sqlitebrowser.org/" target="_blank" rel="noopener">DB Browser for SQLite</a></li>
                    <li><a href="https://developer.android.com/studio/" target="_blank" rel="noopener">Android Studio</a></li>
                    <li><a href="https://www.virtualbox.org/wiki/Downloads" target="_blank" rel="noopener">VirtualBox</a></li>
                </ul>
            </div>

            <p>
                Na versão tratada pelo artigo, o Avilla oferece recursos como backup ADB, downgrade controlado de APK, parser de chats,
                coletas diversas via ADB, decriptação de bases do WhatsApp, integração com IPED, AFLogical e Alias Connector,
                além de utilitários para mídias, geolocalização, hashes e transferência rápida.
            </p>

            <h2>Download e instalação do Avilla Forensics 3.0</h2>
            <p>
                O material orienta obter a ferramenta diretamente em seu repositório oficial no GitHub e mantê-la em
                <code>C:\Forensics</code>, sem espaços no nome da pasta e com execução em modo administrador.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-02-image-01.png') }}" alt="Página do projeto Avilla Forensics no GitHub">
                <figcaption>Figura 1 – Página do projeto Avilla Forensics no GitHub.</figcaption>
            </figure>

            <p>
                O artigo também recomenda criar uma pasta dedicada para salvar aquisições e ter conhecimento prévio de
                forense em dispositivos móveis, já que várias operações exigem compreensão técnica do ambiente Android.
            </p>

            <h3>Pré-requisitos destacados</h3>
            <ul>
                <li>Java para módulos como IPED Tools, Bycode Viewer, Jadx, Backup Extractor e GPS Prune.</li>
                <li>Python para rotinas de MVT, decriptação de bases WhatsApp e ferramentas auxiliares.</li>
                <li>Possível necessidade de configurar paths do sistema para alguns módulos legados.</li>
            </ul>

            <h2>Android Debug Bridge (ADB) no fluxo do Avilla</h2>
            <p>
                O ADB é apresentado como pilar da comunicação com o dispositivo Android, permitindo ações como depuração,
                instalação de apps e ativação de permissões especiais que não ficam disponíveis a aplicativos comuns.
            </p>
            <p>
                Para isso, o artigo mostra o procedimento de habilitação da <strong>Depuração USB</strong>, começando pelo acesso
                às configurações do aparelho e seguindo até as opções do desenvolvedor.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-02-image-02.png') }}" alt="Acesso às configurações do aparelho">
                <figcaption>Figura 2 – Acesso às configurações do dispositivo no roteiro do tutorial.</figcaption>
            </figure>

            <figure class="avilla-figure">
                <img src="{{ $img('page-02-image-03.png') }}" alt="Navegação inicial nas configurações do Android">
                <figcaption>Imagem complementar da navegação inicial nas configurações do Android.</figcaption>
            </figure>

            <figure class="avilla-figure">
                <img src="{{ $img('page-03-image-01.png') }}" alt="Tela sobre o telefone">
                <figcaption>Figura 3 – Caminho até a tela “Sobre o telefone”.</figcaption>
            </figure>

            <figure class="avilla-figure">
                <img src="{{ $img('page-03-image-02.png') }}" alt="Sequência para habilitar opções do desenvolvedor">
                <figcaption>Figura 4 – Sequência visual para habilitar as opções do desenvolvedor e a depuração USB.</figcaption>
            </figure>

            <p>
                Depois de habilitar a depuração, o aparelho exibirá a caixa de autorização de conexão com a chave RSA do computador.
                O texto observa que o caminho exato pode variar conforme fabricante e interface Android.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-03-image-03.png') }}" alt="Permissão de depuração USB">
                <figcaption>Figura 5 – Tela de permissão para depuração USB.</figcaption>
            </figure>

            <h2>Coleta de WhatsApp com Avilla Forensics</h2>
            <h3>APK Downgrade</h3>
            <p>
                O artigo trata o <strong>APK Downgrade</strong> como um procedimento de último caso, com alerta expresso de que pode
                danificar a evidência. Em compensação, ele permite acessar rotinas que ajudam em determinadas extrações lógicas avançadas.
            </p>
            <p>
                Há observações específicas para aparelhos Xiaomi, incluindo desativação de otimizações MIUI e permissões para instalação via USB.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-03-image-04.png') }}" alt="Ícone e área de APK Downgrade">
                <figcaption>Figura 6 – Tela principal do módulo de APK Downgrade.</figcaption>
            </figure>

            <figure class="avilla-figure">
                <img src="{{ $img('page-03-image-05.png') }}" alt="Botões salvar e extrair">
                <figcaption>Figura 7 – Botões de salvar e extrair na operação de APK Downgrade.</figcaption>
            </figure>

            <p>
                O fluxo descrito inclui definição do diretório de saída, detecção dos aplicativos instalados, uso de aplicação teste
                quando necessário e execução de dumps do estado original do app antes da extração.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-04-image-01.png') }}" alt="Informações do arquivo base.apk">
                <figcaption>Figura 8 – Informações do arquivo <code>base.apk</code> gerado no processo.</figcaption>
            </figure>

            <figure class="avilla-figure">
                <img src="{{ $img('page-04-image-02.png') }}" alt="Informações do arquivo AB">
                <figcaption>Figura 9 – Informações do arquivo <code>.ab</code> retornado pela extração.</figcaption>
            </figure>

            <h3>Conversão de .ab para .tar</h3>
            <p>
                Após a coleta, o material orienta o uso do módulo de conversão para transformar o backup em
                <code>.tar</code>, definindo a origem do arquivo <code>.ab</code> e o destino do artefato convertido.
            </p>
            <ul>
                <li>O módulo requer Java.</li>
                <li>Backups com senha podem aumentar bastante o tempo de conversão.</li>
                <li>Alguns ambientes exigem ajuste manual de variáveis do sistema para o path do ADB, grep e da própria suíte.</li>
            </ul>

            <figure class="avilla-figure">
                <img src="{{ $img('page-04-image-03.png') }}" alt="Conversão de AB para TAR">
                <figcaption>Figura 10 – Módulo de conversão de backup <code>.ab</code> para <code>.tar</code>.</figcaption>
            </figure>

            <h3>Parser do WhatsApp</h3>
            <p>
                O artigo também aborda a montagem gráfica de chats a partir do parser do WhatsApp, lembrando que bases mais novas
                podem ter estrutura diferente e nem sempre são interpretadas automaticamente por ferramentas tradicionais.
            </p>
            <p>
                Na rotina de montagem, o operador deve selecionar a pasta destino dos chats, a pasta de avatares e a base
                <code>msgstore.db</code>, além de manter a mídia no mesmo local do processamento.
            </p>

            <figure class="avilla-figure">
                <img src="{{ $img('page-04-image-04.png') }}" alt="Montagem de chats do WhatsApp">
                <figcaption>Figura 11 – Montagem gráfica de chats do WhatsApp dentro do Avilla Forensics.</figcaption>
            </figure>

            <h2>Indexação com IPED</h2>
            <p>
                O conteúdo encerra conectando o Avilla ao <strong>IPED</strong>, destacando a integração com uma ferramenta de código aberto
                voltada ao processamento e análise de evidências digitais em larga escala, com suporte a múltiplos formatos e execução em lote.
            </p>
            <p>
                Entre os pontos ressaltados estão o suporte multiplataforma, a operação portátil sem instalação, a estabilidade do fluxo
                e a capacidade de indexação de grandes volumes de dados para posterior triagem analítica.
            </p>

            <h2>Treinamento oficial</h2>
            <p>
                O artigo informa que a Academia de Forense Digital é o centro autorizado para o treinamento oficial da ferramenta,
                ministrado pelo próprio desenvolvedor, com foco em extração lógica avançada e técnicas como APK downgrade.
            </p>

            <div class="avilla-resource-box">
                <strong>Referências e links úteis:</strong>
                <ul style="margin-top: 12px;">
                    <li><a href="https://github.com/AvillaDaniel/AvillaForensics?tab=readme-ov-file" target="_blank" rel="noopener">Repositório oficial do Avilla Forensics</a></li>
                    <li><a href="https://github.com/sepinf-inc/IPED" target="_blank" rel="noopener">Projeto IPED</a></li>
                    <li><a href="https://academiadeforensedigital.com.br/treinamentos/treinamento-de-avilla-forensics/" target="_blank" rel="noopener">Treinamento oficial Avilla Forensics</a></li>
                    <li><a href="https://academiadeforensedigital.com.br/avilla-forensics-ferramenta-gratuita-de-analise-de-smartphones/" target="_blank" rel="noopener">Matéria original da Academia de Forense Digital</a></li>
                </ul>
            </div>
        </article>
    </div>
</x-filament-panels::page>
