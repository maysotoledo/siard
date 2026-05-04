<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantaoCqhServidor;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantaoCqhServidorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantaoCqhServidor');
    }

    public function view(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('View:PlantaoCqhServidor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantaoCqhServidor');
    }

    public function update(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('Update:PlantaoCqhServidor');
    }

    public function delete(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('Delete:PlantaoCqhServidor');
    }

    public function restore(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('Restore:PlantaoCqhServidor');
    }

    public function forceDelete(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('ForceDelete:PlantaoCqhServidor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlantaoCqhServidor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlantaoCqhServidor');
    }

    public function replicate(AuthUser $authUser, PlantaoCqhServidor $plantaoCqhServidor): bool
    {
        return $authUser->can('Replicate:PlantaoCqhServidor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlantaoCqhServidor');
    }

}