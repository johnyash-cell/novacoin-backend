<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function authenticateAsAdmin(): Admin
{
    return Admin::factory()->create();
}

function adminBearerTokenFor(?Admin $admin = null): string
{
    $admin ??= authenticateAsAdmin();

    return auth('admin')->login($admin);
}

it('lists users with pagination for an authenticated admin', function () {
    $authAdmin = authenticateAsAdmin();
    User::factory()->count(3)->create();

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor($authAdmin))
        ->getJson('/api/admin/users?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonStructure(['data' => [['record_type', 'role', 'email', 'created_at']]]);
});

it('includes admin-only accounts in the users directory listing', function () {
    $authAdmin = authenticateAsAdmin();
    User::factory()->create(['email' => 'member@example.com']);
    Admin::factory()->create(['email' => 'staff-only@example.com']);

    $response = $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor($authAdmin))
        ->getJson('/api/admin/users')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 3);

    expect(collect($response->json('data'))->pluck('email')->all())
        ->toContain('member@example.com', 'staff-only@example.com', $authAdmin->email);
});

it('does not duplicate promoted users as separate admin directory rows', function () {
    $authAdmin = authenticateAsAdmin();
    $user = User::factory()->create(['email' => 'both@example.com']);
    Admin::factory()->create(['email' => $user->email]);

    $response = $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor($authAdmin))
        ->getJson('/api/admin/users')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2);

    $bothRows = collect($response->json('data'))->where('email', 'both@example.com');

    expect($bothRows)->toHaveCount(1);
    expect($bothRows->first()['record_type'])->toBe('user');
    expect($bothRows->first()['role'])->toBe('admin');
    expect($bothRows->first()['has_admin_access'])->toBeTrue();
});

it('orders the users directory by created at descending by default', function () {
    authenticateAsAdmin();
    $olderUser = User::factory()->create([
        'email' => 'older-user@example.com',
        'created_at' => now()->subDays(2),
    ]);
    $newerAdmin = Admin::factory()->create([
        'email' => 'staff-only@example.com',
        'created_at' => now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->getJson('/api/admin/users?sort_by=newest')
        ->assertSuccessful();

    $emails = collect($response->json('data'))->pluck('email')->all();

    expect(array_search($newerAdmin->email, $emails))->toBeLessThan(array_search($olderUser->email, $emails));
});

it('searches users by name or email', function () {
    authenticateAsAdmin();
    User::factory()->create(['first_name' => 'Handy', 'email' => 'handy@example.com']);
    User::factory()->create(['first_name' => 'Salma', 'email' => 'salma@example.com']);

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->getJson('/api/admin/users?search=handy')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'handy@example.com');
});

it('filters users by admin access flag', function () {
    $authAdmin = authenticateAsAdmin();
    $adminUser = User::factory()->create(['email' => 'admin-user@example.com']);
    Admin::factory()->create(['email' => $adminUser->email]);
    User::factory()->create(['email' => 'regular@example.com']);

    $response = $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor($authAdmin))
        ->getJson('/api/admin/users?has_admin_access=1')
        ->assertSuccessful();

    expect(collect($response->json('data'))->pluck('email')->all())
        ->toContain('admin-user@example.com', $authAdmin->email)
        ->not->toContain('regular@example.com');
});

it('returns user filter options for an authenticated admin', function () {
    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->getJson('/api/admin/users/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 1)
        ->assertJsonStructure(['data' => ['filters']]);
});

it('creates a user for an authenticated admin', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->postJson('/api/admin/users', [
            'first_name' => 'New',
            'last_name' => 'Member',
            'email' => 'member@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'phone' => '+15550001111',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'member@example.com')
        ->assertJsonPath('data.has_admin_access', false);

    $this->assertDatabaseHas('users', [
        'email' => 'member@example.com',
    ]);
});

it('shows a single user for an authenticated admin', function () {
    $user = User::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->getJson('/api/admin/users/'.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('updates a user for an authenticated admin', function () {
    $user = User::factory()->create(['first_name' => 'Before']);

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->putJson('/api/admin/users/'.$user->id, [
            'first_name' => 'After',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.first_name', 'After');
});

it('deletes a user for an authenticated admin', function () {
    $user = User::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->deleteJson('/api/admin/users/'.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('message', 'User deleted successfully');

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

it('promotes a user to admin for an authenticated admin', function () {
    $user = User::factory()->create([
        'first_name' => 'Handy',
        'last_name' => 'Okoro',
        'email' => 'handy@example.com',
    ]);

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->postJson('/api/admin/users/'.$user->id.'/promote-to-admin', [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'handy@example.com')
        ->assertJsonPath('data.is_super_admin', false);

    $this->assertDatabaseHas('admins', [
        'email' => 'handy@example.com',
        'first_name' => 'Handy',
        'is_super_admin' => false,
    ]);
});

it('rejects promoting a user who already has admin access', function () {
    $user = User::factory()->create(['email' => 'already-admin@example.com']);
    Admin::factory()->create(['email' => $user->email]);

    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->postJson('/api/admin/users/'.$user->id.'/promote-to-admin', [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This user already has backoffice admin access');
});

it('rejects unauthenticated access to admin user routes', function () {
    $this->getJson('/api/admin/users')->assertUnauthorized();
});

it('returns not found for a missing user', function () {
    $this->withHeader('Authorization', 'Bearer '.adminBearerTokenFor())
        ->getJson('/api/admin/users/999999')
        ->assertNotFound();
});
