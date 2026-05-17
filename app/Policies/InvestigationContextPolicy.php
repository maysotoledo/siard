<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InvestigationContext;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestigationContextPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InvestigationContext');
    }

    public function view(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('View:InvestigationContext');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InvestigationContext');
    }

    public function update(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('Update:InvestigationContext');
    }

    public function delete(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('Delete:InvestigationContext');
    }

    public function restore(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('Restore:InvestigationContext');
    }

    public function forceDelete(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('ForceDelete:InvestigationContext');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InvestigationContext');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InvestigationContext');
    }

    public function replicate(AuthUser $authUser, InvestigationContext $investigationContext): bool
    {
        return $authUser->can('Replicate:InvestigationContext');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InvestigationContext');
    }

}