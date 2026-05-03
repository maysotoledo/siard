<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoPeriodoAquisitivo;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoPeriodoAquisitivoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoPeriodoAquisitivo');
    }

    public function view(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('View:AfastamentoPeriodoAquisitivo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoPeriodoAquisitivo');
    }

    public function update(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('Update:AfastamentoPeriodoAquisitivo');
    }

    public function delete(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('Delete:AfastamentoPeriodoAquisitivo');
    }

    public function restore(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('Restore:AfastamentoPeriodoAquisitivo');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('ForceDelete:AfastamentoPeriodoAquisitivo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoPeriodoAquisitivo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoPeriodoAquisitivo');
    }

    public function replicate(AuthUser $authUser, AfastamentoPeriodoAquisitivo $afastamentoPeriodoAquisitivo): bool
    {
        return $authUser->can('Replicate:AfastamentoPeriodoAquisitivo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoPeriodoAquisitivo');
    }

}