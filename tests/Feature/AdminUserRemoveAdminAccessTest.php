<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function removeAdminAccessToken(?Admin $admin = null): string
{
    return auth('admin')->login($admin ?? Admin::factory()->create());
}

it('removes backoffice admin access from a member', function () {
    $actingAdmin = Admin::factory()->create(['email' => 'operator@example.com']);
    $user = User::factory()->create([
        'email' => 'member-admin@example.com',
        'first_name' => 'Member',
        'last_name' => 'Admin',
    ]);
    Admin::factory()->create([
        'email' => $user->email,
        'is_super_admin' => false,
    ]);

    $this->withHeader('Authorization', 'Bearer '.removeAdminAccessToken($actingAdmin))
        ->postJson('/api/admin/users/'.$user->id.'/remove-admin')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Admin access removed successfully')
        ->assertJsonPath('data.email', 'member-admin@example.com')
        ->assertJsonPath('data.has_admin_access', false);

    $this->assertDatabaseMissing('admins', [
        'email' => 'member-admin@example.com',
    ]);
});

it('rejects removing admin access when the member is not an admin', function () {
    $user = User::factory()->create(['email' => 'plain@example.com']);

    $this->withHeader('Authorization', 'Bearer '.removeAdminAccessToken())
        ->postJson('/api/admin/users/'.$user->id.'/remove-admin')
        ->assertStatus(422)
        ->assertJsonPath('message', 'This user does not have backoffice admin access');
});

it('rejects removing super admin access', function () {
    $actingAdmin = Admin::factory()->create(['email' => 'operator@example.com']);
    $user = User::factory()->create(['email' => 'super@example.com']);
    Admin::factory()->superAdmin()->create(['email' => $user->email]);

    $this->withHeader('Authorization', 'Bearer '.removeAdminAccessToken($actingAdmin))
        ->postJson('/api/admin/users/'.$user->id.'/remove-admin')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Super admin access cannot be removed from this screen');

    $this->assertDatabaseHas('admins', [
        'email' => 'super@example.com',
        'is_super_admin' => true,
    ]);
});

it('rejects removing your own admin access', function () {
    $user = User::factory()->create(['email' => 'self@example.com']);
    $actingAdmin = Admin::factory()->create(['email' => $user->email]);

    $this->withHeader('Authorization', 'Bearer '.removeAdminAccessToken($actingAdmin))
        ->postJson('/api/admin/users/'.$user->id.'/remove-admin')
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot remove your own admin access');

    $this->assertDatabaseHas('admins', [
        'email' => 'self@example.com',
    ]);
});

it('rejects unauthenticated remove-admin requests', function () {
    $user = User::factory()->create();

    $this->postJson('/api/admin/users/'.$user->id.'/remove-admin')
        ->assertUnauthorized();
});
