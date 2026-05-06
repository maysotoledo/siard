<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoSolicitacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoSolicitacaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoSolicitacao');
    }

    public function view(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('View:AfastamentoSolicitacao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoSolicitacao');
    }

    public function update(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('Update:AfastamentoSolicitacao');
    }

    public function delete(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('Delete:AfastamentoSolicitacao');
    }

    public function restore(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('Restore:AfastamentoSolicitacao');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('ForceDelete:AfastamentoSolicitacao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoSolicitacao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoSolicitacao');
    }

    public function replicate(AuthUser $authUser, AfastamentoSolicitacao $afastamentoSolicitacao): bool
    {
        return $authUser->can('Replicate:AfastamentoSolicitacao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoSolicitacao');
    }

}