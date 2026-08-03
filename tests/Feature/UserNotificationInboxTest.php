<?php

use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists in-app notifications for the authenticated user newest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $older = AdminNotification::factory()->create([
        'title' => 'Older notice',
        'message' => 'Older body',
        'sent_at' => now()->subHour(),
    ]);
    $newer = AdminNotification::factory()->create([
        'title' => 'Newer notice',
        'message' => 'Newer body',
        'sent_at' => now(),
    ]);

    AdminNotificationRecipient::factory()->create([
        'admin_notification_id' => $older->id,
        'user_id' => $user->id,
    ]);
    AdminNotificationRecipient::factory()->create([
        'admin_notification_id' => $newer->id,
        'user_id' => $user->id,
    ]);
    AdminNotificationRecipient::factory()->create([
        'admin_notification_id' => $newer->id,
        'user_id' => $otherUser->id,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications?page=1&per_page=20&sort_by=newest')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.unread_count', 2)
        ->assertJsonPath('data.0.title', 'Newer notice')
        ->assertJsonPath('data.0.body', 'Newer body')
        ->assertJsonPath('data.0.is_unread', true)
        ->assertJsonPath('data.1.title', 'Older notice')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'title',
                'body',
                'message',
                'sent_at',
                'is_unread',
                'read_at',
            ]],
        ]);
});

it('shows a single notification owned by the authenticated user', function () {
    $user = User::factory()->create();
    $recipient = AdminNotificationRecipient::factory()->create([
        'user_id' => $user->id,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications/'.$recipient->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $recipient->id)
        ->assertJsonPath('data.title', $recipient->adminNotification->title);
});

it('hides another users notification on show and mark as read', function () {
    $user = User::factory()->create();
    $otherRecipient = AdminNotificationRecipient::factory()->create();

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications/'.$otherRecipient->id)
        ->assertNotFound();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/notifications/'.$otherRecipient->id.'/read')
        ->assertNotFound();
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $recipient = AdminNotificationRecipient::factory()->create([
        'user_id' => $user->id,
        'read_at' => null,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/notifications/'.$recipient->id.'/read')
        ->assertSuccessful()
        ->assertJsonPath('data.is_unread', false);

    expect($recipient->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    AdminNotificationRecipient::factory()->count(2)->create([
        'user_id' => $user->id,
        'read_at' => null,
    ]);
    AdminNotificationRecipient::factory()->create([
        'user_id' => $otherUser->id,
        'read_at' => null,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/notifications/mark-all-as-read')
        ->assertSuccessful()
        ->assertJsonPath('data.marked_count', 2)
        ->assertJsonPath('data.unread_count', 0);

    expect(
        AdminNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count()
    )->toBe(0);

    expect(
        AdminNotificationRecipient::query()
            ->where('user_id', $otherUser->id)
            ->whereNull('read_at')
            ->count()
    )->toBe(1);
});

it('returns unread notification count for the authenticated user', function () {
    $user = User::factory()->create();

    AdminNotificationRecipient::factory()->create([
        'user_id' => $user->id,
        'read_at' => null,
    ]);
    AdminNotificationRecipient::factory()->read()->create([
        'user_id' => $user->id,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications/unread-count')
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 1);
});

it('filters the inbox to unread notifications only', function () {
    $user = User::factory()->create();

    AdminNotificationRecipient::factory()->create([
        'user_id' => $user->id,
        'read_at' => null,
    ]);
    AdminNotificationRecipient::factory()->read()->create([
        'user_id' => $user->id,
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications?unread_only=1')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.is_unread', true);
});

it('rejects unauthenticated access to the user notification inbox', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
    $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/notifications/mark-all-as-read')->assertUnauthorized();
});
