<?php

namespace App\Services\Plantao;

use App\Models\PlantaoDelegadoEscala;
use App\Models\PlantaoEscala;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class PlantaoDeltaImportService
{
    private const MESES = [
        'janeiro' => 1,
        'fevereiro' => 2,
        'marco' => 3,
        'março' => 3,
        'abril' => 4,
        'maio' => 5,
        'junho' => 6,
        'julho' => 7,
        'agosto' => 8,
        'setembro' => 9,
        'outubro' => 10,
        'novembro' => 11,
        'dezembro' => 12,
    ];

    public function importarPdf(string $arquivoPdf, ?int $mes = null, ?int $ano = null, bool $sobrescrever = false, ?array $dadosCorrigidos = null): array
    {
        $texto = $this->extrairTextoPdf($arquivoPdf);
        $detectado = $this->detectarMesAno($texto);
        $mes = $mes ?: ($detectado['mes'] ?? null);
        $ano = $ano ?: ($detectado['ano'] ?? null);
        $registros = $dadosCorrigidos ?: $this->extrairDias($texto, $mes, $ano);
        $validacao = $this->validarImportacao([
            'mes' => $mes,
            'ano' => $ano,
            'registros' => $registros,
        ]);

        if ($validacao['erros'] !== []) {
            app(PlantaoHistoricoService::class)->registrar(
                null,
                'importar_escala_delta',
                'Falha na importação da escala Delta de delegados',
                [
                    'arquivo' => basename($arquivoPdf),
                    'mes' => $mes,
                    'ano' => $ano,
                    'encontrados' => count($registros),
                    'importados' => 0,
                    'ignorados' => 0,
                    'sobrescritos' => 0,
                    'dias_sobrescritos' => [],
                    'avisos' => $validacao['avisos'],
                    'erros' => $validacao['erros'],
                ],
            );

            throw ValidationException::withMessages(['delta_pdf' => implode(' ', $validacao['erros'])]);
        }

        $resultado = $this->salvarEscalaDelegados($registros, $sobrescrever, basename($arquivoPdf));
        $resultado['mes'] = $mes;
        $resultado['ano'] = $ano;
        $resultado['avisos'] = $validacao['avisos'];
        $resultado['erros'] = [];
        $resultado['registros'] = $registros;

        app(PlantaoHistoricoService::class)->registrar(
            null,
            'importar_escala_delta',
            'Importação da escala Delta de delegados',
            [
                'arquivo' => basename($arquivoPdf),
                'mes' => $mes,
                'ano' => $ano,
                'encontrados' => count($registros),
                'importados' => $resultado['importados'],
                'ignorados' => $resultado['ignorados'],
                'sobrescritos' => $resultado['sobrescritos'],
                'dias_sobrescritos' => $resultado['dias_sobrescritos'],
                'avisos' => $validacao['avisos'],
                'erros' => [],
            ],
        );

        return $resultado;
    }

    public function previewPdf(string $arquivoPdf): array
    {
        $texto = $this->extrairTextoPdf($arquivoPdf);
        $detectado = $this->detectarMesAno($texto);
        $registros = $this->extrairDias($texto, $detectado['mes'] ?? null, $detectado['ano'] ?? null);
        $validacao = $this->validarImportacao([
            'mes' => $detectado['mes'] ?? null,
            'ano' => $detectado['ano'] ?? null,
            'registros' => $registros,
        ]);

        return [
            'mes' => $detectado['mes'] ?? null,
            'ano' => $detectado['ano'] ?? null,
            'registros' => $registros,
            'avisos' => $validacao['avisos'],
            'erros' => $validacao['erros'],
        ];
    }

    public function extrairTextoPdf(string $arquivoPdf): string
    {
        return (new Parser())->parseFile($arquivoPdf)->getText();
    }

    public function detectarMesAno(string $texto): array
    {
        if (preg_match('/\b('.implode('|', array_keys(self::MESES)).')\s+((?:20)?\d{2})\b/iu', $texto, $match)) {
            $mesNome = mb_strtolower($match[1]);
            $ano = (int) $match[2];

            return [
                'mes' => self::MESES[$mesNome] ?? null,
                'ano' => $ano < 100 ? 2000 + $ano : $ano,
            ];
        }

        return ['mes' => null, 'ano' => null];
    }

    public function extrairDias(string $texto, ?int $mes = null, ?int $ano = null): array
    {
        if (! $mes || ! $ano) {
            $detectado = $this->detectarMesAno($texto);
            $mes = $mes ?: ($detectado['mes'] ?? null);
            $ano = $ano ?: ($detectado['ano'] ?? null);
        }

        if (! $mes || ! $ano) {
            return [];
        }

        $linhas = preg_split('/\R/u', $texto) ?: [];
        $registros = [];
        $diaAtual = null;
        $buffer = [];

        foreach ($linhas as $linha) {
            $linha = trim(preg_replace('/[ \t]+/u', ' ', $linha) ?? '');

            if ($linha === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2})$/', $linha, $match)) {
                $this->registrarBloco($registros, $diaAtual, $buffer, $mes, $ano);
                $diaAtual = (int) $match[1];
                $buffer = [];
                continue;
            }

            if ($diaAtual !== null) {
                $buffer[] = $linha;
            }
        }

        $this->registrarBloco($registros, $diaAtual, $buffer, $mes, $ano);

        return $registros;
    }

    public function extrairRegistros(string $texto, int $mes, int $ano): array
    {
        return collect($this->extrairDias($texto, $mes, $ano))
            ->map(fn (array $registro): array => [
                'data' => $registro['data_plantao'],
                'nome' => $registro['nome_delegado'],
                'unidade' => $registro['unidade_delegado'],
                'contato' => $registro['contato'],
                'horario' => $registro['horario'],
                'regionalizado' => $registro['regionalizado'],
            ])
            ->all();
    }

    public function normalizarNomeDelegado(string $texto): string
    {
        $texto = preg_replace('/\bREGIONALIZADO\b/iu', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\([^)]*\)/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/[–-]\s*Delegad[ao]\s+de\s+.+$/iu', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\bDelegad[ao]\s+de\s+.+$/iu', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? $texto;

        return mb_strtoupper($texto);
    }

    public function normalizarContato(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? $texto;

        return $texto === '' ? null : $texto;
    }

    public function normalizarUnidade(string $texto): ?string
    {
        $textoPlano = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        if (preg_match('/\((DP[^)]*)\)/iu', $textoPlano, $match)) {
            return mb_strtoupper(trim($match[1]));
        }

        if (preg_match('/[–-]\s*Delegad[ao]\s+de\s+(.+)$/iu', $textoPlano, $match)) {
            return 'DP '.mb_strtoupper(trim($match[1]));
        }

        if (preg_match('/\bDelegad[ao]\s+de\s+(.+)$/iu', $textoPlano, $match)) {
            return 'DP '.mb_strtoupper(trim($match[1]));
        }

        return null;
    }

    public function salvarEscalaDelegados(array $dados, bool $sobrescrever = false, ?string $origemPdf = null): array
    {
        $registros = Arr::get($dados, 'registros', $dados);
        $importados = 0;
        $ignorados = 0;
        $sobrescritos = 0;
        $diasSobrescritos = [];

        foreach ($registros as $registro) {
            $registro = $this->normalizarRegistroParaSalvar($registro, $origemPdf);
            $existente = PlantaoDelegadoEscala::query()
                ->whereDate('data_plantao', $registro['data_plantao'])
                ->first();

            if ($existente && ! $sobrescrever) {
                $ignorados++;
                continue;
            }

            if ($existente) {
                $anterior = $existente->only([
                    'data_plantao',
                    'nome_delegado',
                    'unidade_delegado',
                    'contato',
                    'horario',
                    'regionalizado',
                    'origem_pdf',
                    'dados_extraidos',
                ]);

                $existente->fill($registro)->save();
                $sobrescritos++;
                $diasSobrescritos[] = $registro['data_plantao'];

                app(PlantaoHistoricoService::class)->registrar(
                    PlantaoEscala::query()->whereDate('data_plantao', $registro['data_plantao'])->first(),
                    'sobrescrever_escala_delta',
                    'Delegado da escala Delta sobrescrito',
                    ['anterior' => $anterior, 'novo' => $registro],
                );

                continue;
            }

            PlantaoDelegadoEscala::query()->create($registro);
            $importados++;
        }

        return [
            'encontrados' => count($registros),
            'atualizados' => $importados + $sobrescritos,
            'importados' => $importados,
            'ignorados' => $ignorados,
            'sobrescritos' => $sobrescritos,
            'dias_sobrescritos' => $diasSobrescritos,
        ];
    }

    public function validarImportacao(array $dados): array
    {
        $mes = $dados['mes'] ?? null;
        $ano = $dados['ano'] ?? null;
        $registros = collect($dados['registros'] ?? []);
        $erros = [];
        $avisos = [];

        if (! $mes || ! $ano) {
            $erros[] = 'Não foi possível identificar mês e ano da escala Delta.';
        }

        if ($registros->isEmpty()) {
            $erros[] = 'Nenhum dia com delegado foi identificado no PDF.';
        }

        if ($mes && $ano && $registros->isNotEmpty()) {
            $diasDoMes = Carbon::create((int) $ano, (int) $mes, 1)->daysInMonth;
            $diasEncontrados = $registros
                ->pluck('data_plantao')
                ->filter()
                ->map(fn (string $data): int => Carbon::parse($data)->day)
                ->unique()
                ->sort()
                ->values();
            $faltantes = collect(range(1, $diasDoMes))->diff($diasEncontrados)->values()->all();

            if ($faltantes !== []) {
                $avisos[] = 'Dias sem Delegado identificado: '.implode(', ', $faltantes).'.';
            }
        }

        return ['erros' => $erros, 'avisos' => $avisos];
    }

    private function registrarBloco(array &$registros, ?int $dia, array $linhas, int $mes, int $ano): void
    {
        if (! $dia || $dia < 1 || $dia > Carbon::create($ano, $mes, 1)->daysInMonth) {
            return;
        }

        $bloco = trim(implode("\n", $linhas));

        if ($bloco === '' || ! preg_match('/\bDRA?\.?/iu', $bloco) || ! str_contains(mb_strtolower($bloco), 'contato')) {
            return;
        }

        $antesContato = trim(preg_split('/\bContato\s*:/iu', $bloco, 2)[0] ?? $bloco);
        $contato = $this->normalizarContato($this->extrairLinha($bloco, '/\bContato\s*:\s*(.+)$/ium'));
        $horario = $this->normalizarHorario($this->extrairHorario($bloco));
        $nome = $this->normalizarNomeDelegado($antesContato);

        if ($nome === '') {
            return;
        }

        $registros[] = [
            'data_plantao' => Carbon::create($ano, $mes, $dia)->toDateString(),
            'nome_delegado' => $nome,
            'unidade_delegado' => $this->normalizarUnidade($antesContato),
            'contato' => $contato,
            'horario' => $horario,
            'regionalizado' => preg_match('/\bREGIONALIZADO\b/iu', $bloco) === 1,
            'origem_pdf' => null,
            'dados_extraidos' => [
                'dia' => $dia,
                'texto_original' => $bloco,
                'contato_normalizado' => $this->normalizarContatoBrasileiro($contato),
            ],
        ];
    }

    private function extrairLinha(string $texto, string $regex): ?string
    {
        if (! preg_match($regex, $texto, $match)) {
            return null;
        }

        return trim($match[1]);
    }

    private function normalizarHorario(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = preg_replace('/[)]+$/u', '', trim($texto)) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return $texto === '' ? null : $texto;
    }

    private function extrairHorario(string $bloco): ?string
    {
        $linhas = preg_split('/\R/u', $bloco) ?: [];
        $partes = [];
        $coletando = false;

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            if (preg_match('/Horário\s*:\s*(.+)$/iu', $linha, $match)) {
                $coletando = true;
                $partes[] = $match[1];
                continue;
            }

            if ($coletando) {
                if (preg_match('/^(Documento|Contato\s*:|[◄►]|)/iu', $linha)) {
                    break;
                }

                $partes[] = $linha;
            }
        }

        return $partes === [] ? null : implode(' ', $partes);
    }

    private function normalizarContatoBrasileiro(?string $contato): ?string
    {
        if ($contato === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $contato) ?? '';

        if (strlen($digitos) === 11) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7));
        }

        if (strlen($digitos) === 10) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
        }

        return $contato;
    }

    private function normalizarRegistroParaSalvar(array $registro, ?string $origemPdf): array
    {
        $dadosExtraidos = $registro['dados_extraidos'] ?? [];
        $contato = $this->normalizarContato($registro['contato'] ?? null);
        $dadosExtraidos['contato_normalizado'] = $dadosExtraidos['contato_normalizado'] ?? $this->normalizarContatoBrasileiro($contato);

        return [
            'data_plantao' => Carbon::parse($registro['data_plantao'] ?? $registro['data'] ?? null)->toDateString(),
            'nome_delegado' => mb_strtoupper(trim((string) ($registro['nome_delegado'] ?? $registro['nome'] ?? ''))),
            'unidade_delegado' => filled($registro['unidade_delegado'] ?? null) ? mb_strtoupper(trim((string) $registro['unidade_delegado'])) : null,
            'contato' => $contato,
            'horario' => filled($registro['horario'] ?? null) ? trim((string) $registro['horario']) : null,
            'regionalizado' => (bool) ($registro['regionalizado'] ?? false),
            'origem_pdf' => $origemPdf ?: ($registro['origem_pdf'] ?? null),
            'dados_extraidos' => $dadosExtraidos,
            'importado_por' => Auth::id(),
        ];
    }
}
