<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class TelematicaConexao extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wifi';
    protected static ?string $navigationLabel = 'Conexão';

    protected static string|\UnitEnum|null $navigationGroup = 'Análise Telemática';
    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'telematica/conexao';

    protected string $view = 'filament.pages.telematica-conexao';

    protected static ?string $title = 'CONEXAO';

}
