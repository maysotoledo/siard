<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoPrioridadeRegra;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoPrioridadeRegraPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoPrioridadeRegra');
    }

    public function view(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('View:AfastamentoPrioridadeRegra');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoPrioridadeRegra');
    }

    public function update(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('Update:AfastamentoPrioridadeRegra');
    }

    public function delete(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('Delete:AfastamentoPrioridadeRegra');
    }

    public function restore(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('Restore:AfastamentoPrioridadeRegra');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('ForceDelete:AfastamentoPrioridadeRegra');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoPrioridadeRegra');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoPrioridadeRegra');
    }

    public function replicate(AuthUser $authUser, AfastamentoPrioridadeRegra $afastamentoPrioridadeRegra): bool
    {
        return $authUser->can('Replicate:AfastamentoPrioridadeRegra');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoPrioridadeRegra');
    }

}