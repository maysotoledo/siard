<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaGoogle extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Google';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'telematica/google';

    protected string $view = 'filament.pages.telematica-google';

    protected static ?string $title = 'GOOGLE';

}
