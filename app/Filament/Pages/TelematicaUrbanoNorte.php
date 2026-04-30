<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaUrbanoNorte extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Urbano norte';

    protected static string|\UnitEnum|null $navigationGroup = 'Informação Telemática';
    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'telematica/urbano-norte';

    protected string $view = 'filament.pages.telematica-urbano-norte';

    protected static ?string $title = 'URBANO NORTE';
}
