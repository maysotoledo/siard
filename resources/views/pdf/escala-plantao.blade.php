<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px 18px 18px 18px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.8px; color: #111; }
        .center { text-align: center; }
        .header-wrap { width: 100%; position: relative; min-height: 76px; margin-bottom: 2px; }
        .header { font-size: 9.5px; line-height: 1.18; font-weight: bold; text-transform: uppercase; padding-top: 6px; }
        .header-logo { position: absolute; top: 0; width: 70px; height: 70px; object-fit: contain; }
        .header-logo.left { left: 18px; }
        .header-logo.right { right: 18px; }
        .title { margin: 10px 0 7px; font-size: 12px; font-weight: bold; text-align: center; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 2px 2px; text-align: center; vertical-align: middle; height: 16px; word-wrap: break-word; }
        th { background: #e5e5e5; font-weight: bold; font-size: 7.2px; }
        .day { width: 4%; }
        .week { width: 9%; }
        .name { width: 13%; }
        .epc { width: 10%; }
        .dpc { width: 14%; }
        .contact { width: 10%; }
        .hour { width: 8%; }
        .derf { color: #c00000; font-weight: bold; }
        .obs { margin-top: 8px; font-size: 8px; line-height: 1.28; text-align: justify; }
        .signature { margin-top: 76px; text-align: center; font-size: 10px; }
        .signature-line { width: 260px; margin: 0 auto 4px auto; border-top: 1px solid #111; height: 1px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7px; border-top: 1px solid #111; padding-top: 4px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header-wrap">
        <img class="header-logo left" src="{{ $brasao_mt }}" alt="Brasão Mato Grosso">
        <img class="header-logo right" src="{{ $brasao_pjcmt }}" alt="Brasão PJC-MT">
        <div class="center header">
            ESTADO DE MATO GROSSO<br>
            SECRETARIA DE ESTADO E SEGURANÇA PÚBLICA<br>
            POLÍCIA JUDICIÁRIA CIVIL<br>
            DIRETORIA DO INTERIOR<br>
            DELEGACIA DE POLÍCIA DE CONFRESA
        </div>
    </div>

    <div class="title">ESCALA DE PLANTÃO DE {{ $mes }} DE {{ $ano }}</div>

    <table>
        <thead>
        <tr>
            <th class="day">DIA</th>
            <th class="week">SEMANA</th>
            <th class="name">PLANTÃO IPC</th>
            <th class="name">PLANTÃO IPC</th>
            <th class="epc">EPC</th>
            <th class="dpc">DPC / DELTA</th>
            <th class="contact">CONTATO DPC</th>
            <th class="name">CQH GERAL</th>
            <th class="hour">HORÁRIO</th>
        </tr>
        </thead>
        <tbody>
        @foreach($linhas as $linha)
            <tr>
                <td>{{ $linha['dia'] }}</td>
                <td>{{ $linha['semana'] }}</td>
                <td>{{ $linha['ipc1'] }}</td>
                <td>{{ $linha['ipc2'] }}</td>
                <td>{{ $linha['epc'] }}</td>
                <td>{{ $linha['dpc'] }}</td>
                <td>{{ $linha['dpc_contato'] }}</td>
                <td class="{{ $linha['cqh_derf'] ? 'derf' : '' }}">{{ $linha['cqh'] }}</td>
                <td>{{ $linha['horario'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="obs">
        <strong>Art. 167, I, LC 155 - Das Proibições</strong> - permutar horário de serviço ou executar tarefa sem expressa permissão da autoridade competente; - Obs.: Na falta de um servidor outro que estiver na sequência assume o plantão do dia.
        <br><br>
        <strong>Obrigações do plantão:</strong><br>
        1- Confeccionar os B.Os e receber os B.Os do SROP e da PM;<br>
        2- Tramitar as ocorrências via GEIA-CARTORIUM para autoridade competente;<br>
        3- Cuidar do prédio e patrimônio da delegacia;<br>
        4- Outras normas pertinentes ao plantão.
    </div>

    <div class="page-break"></div>

    <div class="signature">
        {{ $emissao }}<br><br><br><br><br><br>
        <div class="signature-line"></div>
        <strong>{{ mb_strtoupper($delegado_nome) }}</strong><br>
        Delegado de Polícia<br>
        DP CONFRESA
    </div>

    <div class="footer">
        POLÍCIA CIVIL DE MATO GROSSO<br>
        Delegacia Polícia de Confresa – Av. Centro Oeste, nº 535 – Bairro Vila Nova, Confresa-MT. – CEP 78.652-000 –
        Telefone: (66) 3564-1139 – E-mail: mconfresa@pjc.mt.gov.br
    </div>
</body>
</html>
