<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoRegra;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoRegraPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoRegra');
    }

    public function view(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('View:AfastamentoRegra');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoRegra');
    }

    public function update(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('Update:AfastamentoRegra');
    }

    public function delete(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('Delete:AfastamentoRegra');
    }

    public function restore(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('Restore:AfastamentoRegra');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('ForceDelete:AfastamentoRegra');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoRegra');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoRegra');
    }

    public function replicate(AuthUser $authUser, AfastamentoRegra $afastamentoRegra): bool
    {
        return $authUser->can('Replicate:AfastamentoRegra');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoRegra');
    }

}