<?php

namespace App\Filament\Resources\AfastamentoPeriodosAquisitivos\Pages;

use App\Filament\Resources\AfastamentoPeriodosAquisitivos\AfastamentoPeriodoAquisitivoResource;
use App\Enums\TipoAfastamento;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoPeriodoAquisitivoService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAfastamentoPeriodosAquisitivos extends ManageRecords
{
    protected static string $resource = AfastamentoPeriodoAquisitivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('gerar_servidor')
                ->label('Gerar períodos de um servidor')
                ->icon('heroicon-o-sparkles')
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Servidor')
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options(TipoAfastamento::options())
                        ->placeholder('Férias e licença-prêmio'),
                    Forms\Components\Toggle::make('dry_run')->label('Simular sem salvar'),
                    Forms\Components\Toggle::make('force')->label('Forçar atualização dos gerados/manual sem solicitações'),
                ])
                ->action(function (array $data): void {
                    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor(
                        User::query()->findOrFail($data['user_id']),
                        $data['tipo'] ?: null,
                        (bool) ($data['dry_run'] ?? false),
                        (bool) ($data['force'] ?? false),
                    );

                    $this->notificarResumo('Geração concluída', $summary);
                }),
            Actions\Action::make('recalcular_servidor')
                ->label('Recalcular períodos de um servidor')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Servidor')
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options(TipoAfastamento::options())
                        ->placeholder('Férias e licença-prêmio'),
                ])
                ->action(function (array $data): void {
                    $summary = app(AfastamentoPeriodoAquisitivoService::class)->recalcularParaServidor(
                        User::query()->findOrFail($data['user_id']),
                        $data['tipo'] ?: null,
                    );

                    $this->notificarResumo('Recálculo concluído', $summary);
                }),
            Actions\Action::make('gerar_todos')
                ->label('Gerar períodos de todos os servidores')
                ->icon('heroicon-o-users')
                ->requiresConfirmation()
                ->modalDescription('Esta ação percorre todos os servidores e cria ou corrige períodos aquisitivos sem apagar dados existentes.')
                ->form([
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options(TipoAfastamento::options())
                        ->placeholder('Férias e licença-prêmio'),
                    Forms\Components\Toggle::make('dry_run')->label('Simular sem salvar'),
                    Forms\Components\Toggle::make('force')->label('Forçar atualização dos gerados/manual sem solicitações'),
                ])
                ->action(function (array $data): void {
                    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaTodos(
                        $data['tipo'] ?: null,
                        (bool) ($data['dry_run'] ?? false),
                        (bool) ($data['force'] ?? false),
                    );

                    $this->notificarResumo('Geração para todos concluída', $summary);
                }),
            Actions\CreateAction::make(),
        ];
    }

    private function notificarResumo(string $titulo, array $summary): void
    {
        Notification::make()
            ->title($titulo)
            ->body(sprintf(
                '%sCriados: %d | Atualizados: %d | Ignorados: %d | Erros: %d%s',
                ($summary['dry_run'] ?? false) ? 'Simulação: ' : '',
                $summary['criados'] ?? 0,
                $summary['atualizados'] ?? 0,
                $summary['ignorados'] ?? 0,
                $summary['erros'] ?? 0,
                empty($summary['avisos']) ? '' : ' | Avisos: '.count($summary['avisos']),
            ))
            ->success()
            ->send();
    }
}
