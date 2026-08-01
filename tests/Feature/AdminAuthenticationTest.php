<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in an admin with email and password', function () {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email,
        'password' => 'Password1!',
    ])->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.admin.email', 'admin@example.com')
        ->assertJsonPath('data.admin.is_super_admin', false)
        ->assertJsonStructure(['data' => ['token', 'admin']]);
});

it('rejects invalid admin credentials', function () {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid email or password provided');
});

it('returns the authenticated admin profile', function () {
    $admin = Admin::factory()->superAdmin()->create();
    $token = auth('admin')->login($admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', $admin->email)
        ->assertJsonPath('data.is_super_admin', true);
});

it('allows an authenticated admin to create another admin', function () {
    $admin = Admin::factory()->create();
    $token = auth('admin')->login($admin);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/admins', [
            'first_name' => 'New',
            'last_name' => 'Admin',
            'email' => 'new-admin@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'phone' => '+15550001111',
            'is_super_admin' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'new-admin@example.com')
        ->assertJsonPath('data.is_super_admin', false);

    $this->assertDatabaseHas('admins', [
        'email' => 'new-admin@example.com',
        'is_super_admin' => false,
    ]);
});

it('lists admins with pagination for an authenticated admin', function () {
    $admin = Admin::factory()->create();
    Admin::factory()->count(2)->create();
    $token = auth('admin')->login($admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/admins?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 10);
});

it('rejects an admin token on user routes', function () {
    $admin = Admin::factory()->create();
    $token = auth('admin')->login($admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('does not expose a google login route for admins', function () {
    $this->postJson('/api/admin/auth/google', [
        'id_token' => 'fake-token',
    ])->assertNotFound();
});

it('logs out an authenticated admin', function () {
    $admin = Admin::factory()->create();
    $token = auth('admin')->login($admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Admin logged out successfully');
});
