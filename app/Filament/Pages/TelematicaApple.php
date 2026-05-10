<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaApple extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Apple';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'telematica/apple';

    protected string $view = 'filament.pages.telematica-apple';
    
    protected static ?string $title = 'APPLE';

}
