<x-filament-panels::page>
    @php
        $img = fn (string $file) => asset("storage/telematica/iped/{$file}");
    @endphp

    <style>
        .iped-article-wrap {
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.06);
        }

        .iped-article-hero {
            background: linear-gradient(180deg, rgba(3, 105, 161, 0.92), rgba(8, 47, 73, 0.96));
            padding: 22px 22px 0;
        }

        .iped-article-hero img {
            width: 100%;
            display: block;
            border-radius: 24px 24px 0 0;
            object-fit: cover;
            max-height: 420px;
        }

        .iped-article {
            max-width: 860px;
            margin: 0 auto;
            padding: 30px 24px 42px;
            color: #0f172a;
        }

        .iped-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        .iped-page-title {
            font-size: clamp(2rem, 3vw, 2.8rem);
            line-height: 1.08;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin: 0 0 12px;
        }

        .iped-page-subtitle {
            font-size: 1.18rem;
            line-height: 1.55;
            font-weight: 800;
            margin: 0 0 26px;
            color: #0f172a;
        }

        .iped-article h2 {
            font-size: 1.65rem;
            line-height: 1.2;
            font-weight: 900;
            margin: 34px 0 14px;
            letter-spacing: -0.02em;
        }

        .iped-article h3 {
            font-size: 1.15rem;
            line-height: 1.25;
            font-weight: 900;
            margin: 24px 0 10px;
        }

        .iped-article p,
        .iped-article li {
            font-size: 1rem;
            line-height: 1.9;
            color: #334155;
        }

        .iped-article p {
            margin: 0 0 16px;
        }

        .iped-article ul,
        .iped-article ol {
            margin: 0 0 18px 18px;
            padding-left: 18px;
        }

        .iped-inline-note {
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

        .iped-figure {
            margin: 22px 0 26px;
            text-align: center;
        }

        .iped-figure img {
            max-width: 100%;
            height: auto;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .iped-figure figcaption {
            margin-top: 10px;
            font-size: 0.92rem;
            color: #64748b;
            line-height: 1.7;
        }

        .iped-resource-box {
            margin-top: 28px;
            padding: 18px 20px;
            border-radius: 18px;
            border: 1px solid rgba(14, 165, 233, 0.16);
            background: linear-gradient(180deg, #f8fdff 0%, #eef8ff 100%);
        }

        .iped-resource-box a {
            color: #0369a1;
            font-weight: 800;
            text-decoration: underline;
        }

        html.dark .iped-article-wrap {
            background: #0f172a;
            border-color: rgba(148, 163, 184, 0.16);
            box-shadow: none;
        }

        html.dark .iped-article {
            color: #e5eef8;
        }

        html.dark .iped-page-subtitle,
        html.dark .iped-article h2,
        html.dark .iped-article h3 {
            color: #f8fafc;
        }

        html.dark .iped-meta,
        html.dark .iped-figure figcaption {
            color: #94a3b8;
        }

        html.dark .iped-article p,
        html.dark .iped-article li {
            color: #cbd5e1;
        }

        html.dark .iped-inline-note {
            background: rgba(8, 47, 73, 0.58);
            color: #e0f2fe;
            border-left-color: #38bdf8;
        }

        html.dark .iped-figure img {
            border-color: rgba(148, 163, 184, 0.16);
        }

        html.dark .iped-resource-box {
            background: linear-gradient(180deg, rgba(8, 47, 73, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%);
            border-color: rgba(56, 189, 248, 0.2);
        }

        html.dark .iped-resource-box a {
            color: #7dd3fc;
        }

        @media (max-width: 768px) {
            .iped-article {
                padding: 24px 18px 34px;
            }
        }
    </style>

    <div class="iped-article-wrap">
        <div class="iped-article-hero">
            <img src="{{ $img('page-01-image-01.png') }}" alt="O sistema IPED Forense">
        </div>

        <article class="iped-article">


            <h1 class="iped-page-title">O sistema IPED Forense</h1>
            <p class="iped-page-subtitle">O sistema IPED Forense: Processador e Indexador de Evidências Digitais da Polícia Federal</p>

            <h2>Introdução</h2>
            <p>
                Em primeiro lugar, este material apresenta o Sistema IPED Forense como uma ferramenta extremamente versátil,
                com inúmeras possibilidades de uso, parametrizações avançadas e configurações específicas para cenários de
                investigação e perícia digital.
            </p>
            <p>
                O <strong>IPED (Indexador e Processador de Evidências Digitais)</strong>, ou <strong>IPED Digital Forensics Tool</strong>,
                é uma ferramenta forense brasileira desenvolvida em meados de 2012 por <strong>Luís Filipe Nassif</strong>.
                Embora usada pela Polícia Federal desde esse período, ela só foi disponibilizada ao público como projeto
                de código aberto em 2019.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-01-image-02.png') }}" alt="Página GitHub do Serviço de Perícias em Informática">
                <figcaption>Figura 1 – Captura de tela da página GitHub do departamento de Serviço de Perícias em Informática da Polícia Federal Brasileira.</figcaption>
            </figure>

            <span class="iped-inline-note">
                Você vai gostar de ver também:
                <a href="https://www.youtube.com/watch?v=oTwZTZdp2TQ" target="_blank" rel="noopener">Introdução ao IPED Forense, com o Prof. e Diretor Renan Cavalheiro</a>.
            </span>

            <p>
                Segundo o repositório oficial, o IPED foi concebido com foco em velocidade de processamento, justamente
                para dar vazão a grandes volumes de evidências digitais em contextos investigativos complexos.
            </p>

            <h2>Indexação</h2>
            <p>
                O termo indexação se refere à etapa em que a plataforma cria um catálogo contendo palavras e suas localizações
                na prova. Isso viabiliza buscas por palavras-chave com resposta rápida, em contraste com métodos mais antigos,
                que precisavam percorrer a evidência do zero a cada nova consulta.
            </p>
            <p>
                Nesse contexto, o IPED passou a ser essencial para investigações com grande massa probatória digital,
                oferecendo agilidade e organização para análise posterior.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-01-image-03.png') }}" alt="Estrutura da ferramenta IPED">
                <figcaption>Figura 2 – Estrutura da ferramenta IPED conforme abstração do Professor Renan Cavalheiro.</figcaption>
            </figure>

            <p>
                Com a abertura do projeto, a ferramenta rapidamente se tornou uma das mais populares no cenário mundial,
                com referências de uso em instituições como FBI, Interpol e Forças Armadas.
            </p>

            <h2>Configurando o Java antes de usar a ferramenta IPED</h2>
            <p>
                O material orienta instalar o <strong>Java Development Toolkit (JDK)</strong> antes do uso do sistema.
                A recomendação apontada no conteúdo é utilizar o <strong>Java 11 LTS</strong>, preferencialmente pela Bellsoft.
            </p>

            <div class="iped-resource-box">
                <a href="https://bell-sw.com/pages/downloads/#/java-11-lts" target="_blank" rel="noopener">Baixar Bellsoft Java 11 LTS</a>
            </div>

            <h2>IPED Download – Fazendo o download do sistema IPED</h2>
            <p>
                A forma mais usual de obter o IPED é pela área de <strong>Releases</strong> do GitHub do projeto, onde fica
                disponível o pacote com a ferramenta e plugins associados.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-02-image-02.png') }}" alt="Página do projeto IPED no GitHub">
                <figcaption>Figura 3 – Página GitHub do Projeto IPED.</figcaption>
            </figure>

            <p>
                Após acessar a release correspondente, deve-se navegar até a seção <strong>assets</strong> para localizar
                o arquivo principal de download.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-02-image-03.png') }}" alt="Assets para download">
                <figcaption>Figura 4 – Indicação do arquivo contendo o IPED Forense para download.</figcaption>
            </figure>

            <p>
                Depois do download, a orientação é extrair o conteúdo do ZIP para uma pasta de fácil acesso, como <code>C:\IPED</code>.
                O material descreve quatro diretórios principais na estrutura inicial:
            </p>

            <ul>
                <li>Regripper</li>
                <li>MPlayer</li>
                <li>Plugins</li>
                <li>Iped-06</li>
            </ul>

            <p>
                Esses diretórios reúnem módulos de apoio, plugins e a pasta principal da ferramenta. O sistema se divide
                entre o módulo de processamento/indexação e o módulo de análise, conhecido como <strong>IPED SearchApp</strong>.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-02-image-04.png') }}" alt="Comando iped.exe -h">
                <figcaption>Figura 5 – Linha de comando para exibição da página de ajuda do IPED Forense através do binário Windows.</figcaption>
            </figure>

            <p>
                O mesmo comportamento pode ser obtido quando utilizada a biblioteca Java, com o comando
                <code>java -jar iped.jar -h</code>.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-02-image-05.png') }}" alt="Comando java -jar iped.jar -h">
                <figcaption>Figura 6 – Linha de comando para exibição da página de ajuda do IPED Forense através do uso da biblioteca iped.jar.</figcaption>
            </figure>

            <h2>Operando o sistema IPED Forense: Indexador e Processador de Evidências Digitais</h2>
            <p>
                O uso básico da ferramenta exige pelo menos três parâmetros centrais:
            </p>
            <ol>
                <li>Dados de origem para processamento, geralmente uma imagem forense.</li>
                <li>Pasta de saída do projeto onde serão salvos os arquivos resultantes.</li>
                <li>Perfil de processamento a ser usado.</li>
            </ol>

            <h3>Item 1</h3>
            <p>
                Os dados de origem são controlados pelo parâmetro <code>-data</code> ou <code>-d</code>, que aponta para o objeto
                a ser processado. Isso pode incluir unidade física, imagem forense, arquivos ou pastas individuais.
            </p>
            <p>
                Entre os formatos citados no material estão discos físicos e virtuais, imagens RAW/DD/001/ISO, E01, EX01, AD1 e
                estruturas segmentadas ou sequenciais.
            </p>

            <h3>Item 2</h3>
            <p>
                A pasta de saída é indicada por <code>-output</code> ou <code>-o</code>. É nesse local que ficam o banco de indexação,
                arquivos exportados e demais artefatos utilizados na fase de análise.
            </p>

            <h3>Item 3</h3>
            <p>
                O perfil de processamento, definido com <code>-p</code>, seleciona o conjunto de parametrizações aplicadas à evidência.
                O material observa que o IPED traz perfis prontos e também permite personalização.
            </p>

            <h3>a) Padrão</h3>
            <p>
                Usa hashes MD5, SHA-1 e SHA-256, expande arquivos compostos, indexa dados e metadados, gera miniaturas e executa buscas
                por padrões de expressões regulares. Não ativa carving nem indexação de slackspace e área não alocada.
            </p>

            <h3>b) Cego</h3>
            <p>
                Voltado a extrações “cegas”, útil quando o objetivo é garantir apenas material potencialmente relevante antes da análise integral.
            </p>

            <h3>c) Modo rápido</h3>
            <p>
                Perfil pensado para agilidade em campo, com pré-visualização rápida de dados e organização por estrutura de pastas e categorias.
            </p>

            <h3>d) Forense</h3>
            <p>
                É o perfil mais completo entre os pré-configurados, somando carving, indexação de slackspace, área não alocada e comparação contra bibliotecas de hash.
            </p>

            <h3>e) Pedo</h3>
            <p>
                Direcionado à triagem de imagens relacionadas a crimes contra crianças e adolescentes por comparação com bibliotecas de hash.
            </p>

            <h3>f) Triagem</h3>
            <p>
                Semelhante ao fastmode, mas com indexação direcionada a categorias como Office, PDF, e-mails e arquivos de texto.
            </p>

            <p>
                Como exemplo de sintaxe básica, o material apresenta:
                <code>java -jar iped.jar -d imagemForense.E01 -o ProjetoIPED -p forensic</code>.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-03-image-01.png') }}" alt="Sintaxe básica do IPED">
                <figcaption>Figura 7 – Sintaxe básica de execução da ferramenta IPED.</figcaption>
            </figure>

            <p>
                Se tudo ocorrer corretamente, o sistema cria a pasta de projeto com o binário do SearchApp, banco de dados do sleuthkit
                e demais artefatos definidos pelo perfil utilizado.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-03-image-02.png') }}" alt="Arquivos resultantes do processamento">
                <figcaption>Figura 8 – Arquivos resultantes do processamento com IPED Forense.</figcaption>
            </figure>

            <p>
                Para iniciar a análise das evidências processadas pela ferramenta IPED, basta executar o <strong>IPED SearchApp</strong>.
            </p>

            <h2>g) Analisando Evidências com o IPED SearchApp</h2>
            <p>
                Assim como o processador, o SearchApp é uma ferramenta muito poderosa. Ele permite cruzar filtros por recurso de
                processamento, categoria, árvore de diretórios, metadados e palavras-chave, produzindo um efeito de funil analítico.
            </p>

            <span class="iped-inline-note">
                Você vai gostar de ver também:
                <a href="https://www.youtube.com/watch?v=oTwZTZdp2TQ" target="_blank" rel="noopener">Introdução ao IPED Forense, com o Prof. e Diretor Renan Cavalheiro</a>.
            </span>

            <figure class="iped-figure">
                <img src="{{ $img('page-03-image-03.png') }}" alt="Layout do IPED SearchApp">
                <figcaption>Figura 9 – Layout da ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>1. Filtro por características resultantes do processamento</h3>
            <p>
                Localizado no canto superior esquerdo, permite filtrar arquivos por características como itens ativos, recuperados por carving,
                deletados em nível de sistema de arquivos, hashes conhecidos e outros sinais derivados do processamento.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-03-image-04.png') }}" alt="Filtro por características do processamento">
                <figcaption>Figura 10 – Filtros por recurso de processamento da ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>2. Filtro de Categorias</h3>
            <p>
                Permite isolar arquivos com base em sua categorização, como derivados de internet, bases de dados, documentos,
                arquivos compactados, e-mails e multimídia.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-01.png') }}" alt="Filtro de categorias">
                <figcaption>Figura 11 – Filtros por categorias da ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>3. Filtro de evidência</h3>
            <p>
                Permite navegar pela estrutura da evidência e usar essa navegação como critério adicional em conjunto com os demais filtros.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-02.png') }}" alt="Filtro por árvore de diretórios">
                <figcaption>Figura 12 – Filtros por árvore de diretórios da ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>4. Área de Resultados</h3>
            <p>
                Exibe os itens resultantes da aplicação dos filtros em diferentes formatos, como tabela, galeria, mapa e vínculos.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-04.png') }}" alt="Resultados em tabela">
                <figcaption>Figura 13 – Resultados em formato de tabela.</figcaption>
            </figure>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-05.png') }}" alt="Resultados em galeria">
                <figcaption>Figura 14 – Resultados em formato de galeria de imagens.</figcaption>
            </figure>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-06.png') }}" alt="Resultados em mapa GPS">
                <figcaption>Figura 15 – Resultados em formato de mapeamento GPS.</figcaption>
            </figure>

            <figure class="iped-figure">
                <img src="{{ $img('page-04-image-07.png') }}" alt="Resultados em vínculos">
                <figcaption>Figura 16 – Área de resultados em formato de vínculos.</figcaption>
            </figure>

            <h3>5. Área de busca</h3>
            <p>
                Localizada na região central superior da interface, permite buscas por palavras-chave em conjunto com os demais filtros.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-05-image-01.png') }}" alt="Área de busca">
                <figcaption>Figura 17 – Área de buscas por palavras-chave na ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>6. Filtro por Marcadores</h3>
            <p>
                Permite destacar arquivos relevantes durante a análise, com criação de marcadores personalizados e filtro por itens marcados.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-05-image-02.png') }}" alt="Filtro por marcadores">
                <figcaption>Figura 18 – Filtro por arquivos marcados na ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>7. Filtro por metadados</h3>
            <p>
                Um dos recursos mais poderosos do SearchApp, permitindo identificação rápida por nome, hash, timestamps e outras categorias de metadados.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-05-image-03.png') }}" alt="Filtro por metadados">
                <figcaption>Figura 19 – Filtro de metadados na ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>8. Painel Auxiliar</h3>
            <p>
                Exibe sinais importantes da análise, como incidências de palavras-chave, anexos, duplicados e referências do item selecionado.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-05-image-04.png') }}" alt="Painel auxiliar">
                <figcaption>Figura 20 – Painel auxiliar na ferramenta IPED SearchApp.</figcaption>
            </figure>

            <h3>9. Painel de Conteúdo</h3>
            <p>
                Permite visualizar o conteúdo dos arquivos em hexadecimal, texto decodificado, metadados específicos ou pré-visualização.
            </p>

            <figure class="iped-figure">
                <img src="{{ $img('page-05-image-05.png') }}" alt="Painel de conteúdo">
                <figcaption>Figura 21 – Conteúdo do IPED SearchApp.</figcaption>
            </figure>

            <h2>Conclusão</h2>
            <p>
                O material conclui que o IPED é um dos melhores sistemas do mundo para processamento, indexação e análise de evidências digitais,
                sobretudo pela capacidade de tratar grandes volumes de dados com agilidade e profundidade analítica.
            </p>

            <div class="iped-resource-box">
                <strong>Referências e links úteis:</strong>
                <ul style="margin-top: 12px;">
                    <li><a href="https://github.com/sepinf-inc/IPED/" target="_blank" rel="noopener">GitHub do Projeto IPED</a></li>
                    <li><a href="https://github.com/sepinf-inc/IPED/wiki/Beginner%E2%80%99s-Start-Guide" target="_blank" rel="noopener">Guia do iniciante do IPED</a></li>
                    <li><a href="https://github.com/thiagofuer/IPEDTools_Releases/releases" target="_blank" rel="noopener">IPEDTools</a></li>
                    <li><a href="{{ asset('storage/telematica/iped/buscaporfaceiped.pdf') }}" download>Busca por face</a></li>
                    <li><a href="{{ asset('storage/telematica/iped/ipedacadepol.pdf') }}" download>Apostila IPED Acadepol</a></li>
                    <li><a href="https://academiadeforensedigital.com.br/sistema-iped-forense/" target="_blank" rel="noopener">Matéria original da Academia de Forense Digital</a></li>
                </ul>
            </div>
        </article>
    </div>
</x-filament-panels::page>
