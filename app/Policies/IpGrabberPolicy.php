<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\IpGrabber;
use Illuminate\Auth\Access\HandlesAuthorization;

class IpGrabberPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IpGrabber');
    }

    public function view(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('View:IpGrabber');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IpGrabber');
    }

    public function update(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('Update:IpGrabber');
    }

    public function delete(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('Delete:IpGrabber');
    }

    public function restore(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('Restore:IpGrabber');
    }

    public function forceDelete(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('ForceDelete:IpGrabber');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IpGrabber');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IpGrabber');
    }

    public function replicate(AuthUser $authUser, IpGrabber $ipGrabber): bool
    {
        return $authUser->can('Replicate:IpGrabber');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IpGrabber');
    }

}