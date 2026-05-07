<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PixelTrack;
use Illuminate\Auth\Access\HandlesAuthorization;

class PixelTrackPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PixelTrack');
    }

    public function view(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('View:PixelTrack');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PixelTrack');
    }

    public function update(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('Update:PixelTrack');
    }

    public function delete(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('Delete:PixelTrack');
    }

    public function restore(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('Restore:PixelTrack');
    }

    public function forceDelete(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('ForceDelete:PixelTrack');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PixelTrack');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PixelTrack');
    }

    public function replicate(AuthUser $authUser, PixelTrack $pixelTrack): bool
    {
        return $authUser->can('Replicate:PixelTrack');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PixelTrack');
    }

}