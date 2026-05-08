<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PixelSubscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class PixelSubscriptionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PixelSubscription');
    }

    public function view(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('View:PixelSubscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PixelSubscription');
    }

    public function update(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('Update:PixelSubscription');
    }

    public function delete(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('Delete:PixelSubscription');
    }

    public function restore(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('Restore:PixelSubscription');
    }

    public function forceDelete(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('ForceDelete:PixelSubscription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PixelSubscription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PixelSubscription');
    }

    public function replicate(AuthUser $authUser, PixelSubscription $pixelSubscription): bool
    {
        return $authUser->can('Replicate:PixelSubscription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PixelSubscription');
    }

}