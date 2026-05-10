<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesPlatformLogAnalysis;
use App\Services\AnaliseInteligente\Google\GoogleLogParser;
use App\Services\AnaliseInteligente\Google\GoogleReportAggregator;
use App\Services\AnaliseInteligente\Platform\PlatformLogParser;
use App\Services\AnaliseInteligente\Platform\PlatformReportAggregator;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class AnaliseInteligenteGoogle extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use HandlesPlatformLogAnalysis;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Análise log GOOGLE';
    protected static ?string $title = 'Análise de log do GOOGLE';
    protected static ?string $slug = 'analise-inteligente-google';

    protected string $view = 'filament.pages.analise-inteligente-platform';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    protected function platformSource(): string
    {
        return 'google';
    }

    protected function platformLabel(): string
    {
        return 'Google';
    }

    protected function makeLogParser(): PlatformLogParser
    {
        return new GoogleLogParser();
    }

    protected function makeReportAggregator(): PlatformReportAggregator
    {
        return new GoogleReportAggregator();
    }
}
