<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaAvilla extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Avilla Forensics';

    protected static string|\UnitEnum|null $navigationGroup = 'Informação Telemática';
    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'telematica/avilla';

    protected string $view = 'filament.pages.telematica-avilla';

    protected static ?string $title = 'Avilla Forensics';
}
