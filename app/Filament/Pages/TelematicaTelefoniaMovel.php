<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaTelefoniaMovel extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Telefonia Móvel';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'telematica/telefonia-movel';

    protected string $view = 'filament.pages.telematica-telefonia-movel';

    protected static ?string $title = 'TELEFONIA MOVEL';

}
