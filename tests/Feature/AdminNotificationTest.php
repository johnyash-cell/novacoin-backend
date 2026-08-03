<?php

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends an in-app notification to all users', function () {
    $users = User::factory()->count(3)->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => 'Maintenance window tonight',
            'message' => 'Expect brief downtime after midnight.',
            'audience_mode' => 'all_users',
            'delivery' => 'send_now',
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.title', 'Maintenance window tonight')
        ->assertJsonPath('data.message', 'Expect brief downtime after midnight.')
        ->assertJsonPath('data.audience_mode', 'all_users')
        ->assertJsonPath('data.audience_label', 'All users')
        ->assertJsonPath('data.audience_count', 3)
        ->assertJsonPath('data.delivery', 'send_now');

    $this->assertDatabaseCount('admin_notifications', 1);
    $this->assertDatabaseCount('admin_notification_recipients', 3);

    foreach ($users as $user) {
        $this->assertDatabaseHas('admin_notification_recipients', [
            'user_id' => $user->id,
        ]);
    }
});

it('sends an in-app notification to selected users only', function () {
    $selectedUsers = User::factory()->count(2)->create();
    User::factory()->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => 'Wallet update',
            'message' => 'Your wallet features were updated.',
            'audience_mode' => 'selected_users',
            'user_ids' => $selectedUsers->pluck('id')->all(),
            'delivery' => 'send_now',
        ])
        ->assertCreated()
        ->assertJsonPath('data.audience_mode', 'selected_users')
        ->assertJsonPath('data.audience_label', 'Selected users')
        ->assertJsonPath('data.audience_count', 2);

    $this->assertDatabaseCount('admin_notification_recipients', 2);
});

it('requires at least one user id when audience mode is selected users', function () {
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => 'Wallet update',
            'message' => 'Your wallet features were updated.',
            'audience_mode' => 'selected_users',
            'delivery' => 'send_now',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);
});

it('rejects send to all users when there are no members', function () {
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => 'Hello',
            'message' => 'Welcome to NovaCoin.',
            'audience_mode' => 'all_users',
            'delivery' => 'send_now',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audience_mode']);
});

it('validates title and message length limits', function () {
    User::factory()->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => str_repeat('a', 121),
            'message' => str_repeat('b', 501),
            'audience_mode' => 'all_users',
            'delivery' => 'send_now',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'message']);
});

it('rejects unsupported delivery modes', function () {
    User::factory()->create();
    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/notifications', [
            'title' => 'Hello',
            'message' => 'Welcome to NovaCoin.',
            'audience_mode' => 'all_users',
            'delivery' => 'schedule',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery']);
});

it('lists notifications sent by all admins newest first', function () {
    $firstAdmin = Admin::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Admin',
        'email' => 'ada@novacoin.test',
    ]);
    $secondAdmin = Admin::factory()->create();

    AdminNotification::factory()->create([
        'admin_id' => $firstAdmin->id,
        'title' => 'Older notice',
        'sent_at' => now()->subHour(),
    ]);
    AdminNotification::factory()->create([
        'admin_id' => $secondAdmin->id,
        'title' => 'Newer notice',
        'sent_at' => now(),
    ]);

    $token = auth('admin')->login($firstAdmin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/notifications?page=1&per_page=10&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.title', 'Newer notice')
        ->assertJsonPath('data.1.title', 'Older notice')
        ->assertJsonPath('data.1.sent_by.email', 'ada@novacoin.test')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'title',
                'message',
                'audience_mode',
                'audience_label',
                'audience_count',
                'delivery',
                'sent_at',
                'sent_by',
            ]],
        ]);
});

it('returns notification filter options for an authenticated admin', function () {
    AdminNotification::factory()->create(['audience_mode' => 'all_users']);
    AdminNotification::factory()->create(['audience_mode' => 'selected_users']);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/notifications/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 2);
});

it('rejects unauthenticated access to admin notifications', function () {
    $this->getJson('/api/admin/notifications')->assertUnauthorized();
    $this->postJson('/api/admin/notifications', [])->assertUnauthorized();
});
