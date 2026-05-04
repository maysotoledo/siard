<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantaoCqhExterno;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantaoCqhExternoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantaoCqhExterno');
    }

    public function view(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('View:PlantaoCqhExterno');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantaoCqhExterno');
    }

    public function update(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('Update:PlantaoCqhExterno');
    }

    public function delete(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('Delete:PlantaoCqhExterno');
    }

    public function restore(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('Restore:PlantaoCqhExterno');
    }

    public function forceDelete(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('ForceDelete:PlantaoCqhExterno');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlantaoCqhExterno');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlantaoCqhExterno');
    }

    public function replicate(AuthUser $authUser, PlantaoCqhExterno $plantaoCqhExterno): bool
    {
        return $authUser->can('Replicate:PlantaoCqhExterno');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlantaoCqhExterno');
    }

}