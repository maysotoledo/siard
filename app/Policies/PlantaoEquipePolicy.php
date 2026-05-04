<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantaoEquipe;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantaoEquipePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantaoEquipe');
    }

    public function view(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('View:PlantaoEquipe');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantaoEquipe');
    }

    public function update(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('Update:PlantaoEquipe');
    }

    public function delete(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('Delete:PlantaoEquipe');
    }

    public function restore(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('Restore:PlantaoEquipe');
    }

    public function forceDelete(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('ForceDelete:PlantaoEquipe');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlantaoEquipe');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlantaoEquipe');
    }

    public function replicate(AuthUser $authUser, PlantaoEquipe $plantaoEquipe): bool
    {
        return $authUser->can('Replicate:PlantaoEquipe');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlantaoEquipe');
    }

}