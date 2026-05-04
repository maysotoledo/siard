<?php

namespace App\Http\Controllers;

use App\Services\Plantao\PlantaoPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlantaoPdfController
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->can('gerar_pdf_plantao') || $request->user()?->hasRole('admin') || $request->user()?->hasRole('super_admin'), 403);

        $mes = (int) $request->query('mes', now()->month);
        $ano = (int) $request->query('ano', now()->year);
        try {
            $data = app(PlantaoPdfService::class)->dadosMensais($mes, $ano);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return Pdf::loadView('pdf.escala-plantao', $data)
            ->setPaper('a4', 'portrait')
            ->download("escala-plantao-{$mes}-{$ano}.pdf");
    }
}
