<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AiChat;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiChatPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AiChat');
    }

    public function view(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('View:AiChat');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AiChat');
    }

    public function update(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('Update:AiChat');
    }

    public function delete(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('Delete:AiChat');
    }

    public function restore(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('Restore:AiChat');
    }

    public function forceDelete(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('ForceDelete:AiChat');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AiChat');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AiChat');
    }

    public function replicate(AuthUser $authUser, AiChat $aiChat): bool
    {
        return $authUser->can('Replicate:AiChat');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AiChat');
    }

}