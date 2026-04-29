<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaIped extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'IPED';

    protected static string|\UnitEnum|null $navigationGroup = 'Informação Telemática';
    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'telematica/iped';

    protected string $view = 'filament.pages.telematica-iped';

    protected static ?string $title = 'IPED';
}
