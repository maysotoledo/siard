<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoPeriodoBloqueado;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoPeriodoBloqueadoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoPeriodoBloqueado');
    }

    public function view(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('View:AfastamentoPeriodoBloqueado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoPeriodoBloqueado');
    }

    public function update(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('Update:AfastamentoPeriodoBloqueado');
    }

    public function delete(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('Delete:AfastamentoPeriodoBloqueado');
    }

    public function restore(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('Restore:AfastamentoPeriodoBloqueado');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('ForceDelete:AfastamentoPeriodoBloqueado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoPeriodoBloqueado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoPeriodoBloqueado');
    }

    public function replicate(AuthUser $authUser, AfastamentoPeriodoBloqueado $afastamentoPeriodoBloqueado): bool
    {
        return $authUser->can('Replicate:AfastamentoPeriodoBloqueado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoPeriodoBloqueado');
    }

}