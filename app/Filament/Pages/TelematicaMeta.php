<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaMeta extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Meta (Facebook/Instagram)';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'telematica/meta';

    protected string $view = 'filament.pages.telematica-meta';

    protected static ?string $title = 'META';

}
