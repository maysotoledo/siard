<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantaoPermuta;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantaoPermutaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantaoPermuta');
    }

    public function view(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('View:PlantaoPermuta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantaoPermuta');
    }

    public function update(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('Update:PlantaoPermuta');
    }

    public function delete(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('Delete:PlantaoPermuta');
    }

    public function restore(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('Restore:PlantaoPermuta');
    }

    public function forceDelete(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('ForceDelete:PlantaoPermuta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlantaoPermuta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlantaoPermuta');
    }

    public function replicate(AuthUser $authUser, PlantaoPermuta $plantaoPermuta): bool
    {
        return $authUser->can('Replicate:PlantaoPermuta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlantaoPermuta');
    }

}