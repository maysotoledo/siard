<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('admin');
    Permission::findOrCreate('View:User');
    Permission::findOrCreate('ViewAny:User');
    Permission::findOrCreate('Update:User');
});

it('esconde usuarios super_admin da resource para quem nao e super_admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($admin);

    expect(UserResource::getEloquentQuery()->pluck('id')->all())->not->toContain($superAdmin->id);
});

it('permite super_admin visualizar outros super_admin na resource', function (): void {
    $auth = User::factory()->create();
    $auth->assignRole('super_admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($auth);

    expect(UserResource::getEloquentQuery()->pluck('id')->all())->toContain($superAdmin->id);
});

it('nega view e update de usuario super_admin para usuario comum mesmo com permissao', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(['View:User', 'Update:User']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $policy = app(UserPolicy::class);

    expect($policy->view($admin, $superAdmin))->toBeFalse()
        ->and($policy->update($admin, $superAdmin))->toBeFalse();
});
