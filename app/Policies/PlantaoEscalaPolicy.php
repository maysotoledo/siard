<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantaoEscala;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantaoEscalaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantaoEscala');
    }

    public function view(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('View:PlantaoEscala');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantaoEscala');
    }

    public function update(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('Update:PlantaoEscala');
    }

    public function delete(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('Delete:PlantaoEscala');
    }

    public function restore(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('Restore:PlantaoEscala');
    }

    public function forceDelete(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('ForceDelete:PlantaoEscala');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlantaoEscala');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlantaoEscala');
    }

    public function replicate(AuthUser $authUser, PlantaoEscala $plantaoEscala): bool
    {
        return $authUser->can('Replicate:PlantaoEscala');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlantaoEscala');
    }

}