<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaBancos extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Bancos';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'telematica/bancos';

    protected string $view = 'filament.pages.telematica-bancos';

    protected static ?string $title = 'BANCOS';

}
