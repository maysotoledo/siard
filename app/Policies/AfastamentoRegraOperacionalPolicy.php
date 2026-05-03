<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AfastamentoRegraOperacional;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfastamentoRegraOperacionalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AfastamentoRegraOperacional');
    }

    public function view(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('View:AfastamentoRegraOperacional');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AfastamentoRegraOperacional');
    }

    public function update(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('Update:AfastamentoRegraOperacional');
    }

    public function delete(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('Delete:AfastamentoRegraOperacional');
    }

    public function restore(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('Restore:AfastamentoRegraOperacional');
    }

    public function forceDelete(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('ForceDelete:AfastamentoRegraOperacional');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AfastamentoRegraOperacional');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AfastamentoRegraOperacional');
    }

    public function replicate(AuthUser $authUser, AfastamentoRegraOperacional $afastamentoRegraOperacional): bool
    {
        return $authUser->can('Replicate:AfastamentoRegraOperacional');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AfastamentoRegraOperacional');
    }

}