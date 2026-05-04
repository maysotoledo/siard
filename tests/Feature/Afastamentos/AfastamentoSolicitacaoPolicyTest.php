<?php

use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use App\Policies\AfastamentoSolicitacaoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function solicitacaoDoUsuario(User $user, StatusAfastamento $status): AfastamentoSolicitacao
{
    return AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => $status->value,
    ]);
}

it('permite usuario excluir proprio afastamento cancelado ou indeferido', function (): void {
    $user = User::factory()->create();
    $policy = app(AfastamentoSolicitacaoPolicy::class);

    expect($policy->delete($user, solicitacaoDoUsuario($user, StatusAfastamento::CANCELADO)))->toBeTrue()
        ->and($policy->delete($user, solicitacaoDoUsuario($user, StatusAfastamento::INDEFERIDO)))->toBeTrue();
});

it('nao permite usuario excluir proprio afastamento em outros status', function (): void {
    $user = User::factory()->create();

    expect(app(AfastamentoSolicitacaoPolicy::class)->delete($user, solicitacaoDoUsuario($user, StatusAfastamento::SOLICITADO)))->toBeFalse();
});

it('nao permite usuario excluir afastamento cancelado de outro servidor sem permissao', function (): void {
    $user = User::factory()->create();
    $outro = User::factory()->create();

    expect(app(AfastamentoSolicitacaoPolicy::class)->delete($user, solicitacaoDoUsuario($outro, StatusAfastamento::CANCELADO)))->toBeFalse();
});
