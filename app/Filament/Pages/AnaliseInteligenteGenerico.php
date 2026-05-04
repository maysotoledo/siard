<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesPlatformLogAnalysis;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class AnaliseInteligenteGenerico extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use HandlesPlatformLogAnalysis;

    protected static ?string $navigationLabel = 'Análise log GENÉRICO';
    protected static ?string $title = 'Análise de log genérico';
    protected static ?string $slug = 'analise-inteligente-generico';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected string $view = 'filament.pages.analise-inteligente-platform';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Análise Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    protected function platformSource(): string
    {
        return 'generico';
    }

    protected function platformLabel(): string
    {
        return 'Genérico';
    }

    protected function uploadDirectory(): string
    {
        return 'uploads/generic-logs';
    }
}
