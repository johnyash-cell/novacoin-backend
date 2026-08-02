<?php

use App\Models\Admin;
use App\Models\User;
use App\Models\UserPageVisitLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records a page visit for an authenticated user', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/activity/page-visits', [
            'page_path' => '/dashboard',
            'page_title' => 'Dashboard',
            'referrer' => '/login',
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.page_path', '/dashboard')
        ->assertJsonPath('data.user_id', $user->id);

    $this->assertDatabaseHas('user_page_visit_logs', [
        'user_id' => $user->id,
        'page_path' => '/dashboard',
        'page_title' => 'Dashboard',
        'device_type' => 'desktop',
    ]);
});

it('records an anonymous page visit without authentication', function () {
    $this->postJson('/api/activity/page-visits', [
        'page_path' => '/login',
        'page_title' => 'Sign in',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', null);

    $this->assertDatabaseHas('user_page_visit_logs', [
        'user_id' => null,
        'page_path' => '/login',
    ]);
});

it('captures ip address and user agent when recording a page visit', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
        ->postJson('/api/activity/page-visits', [
            'page_path' => '/investments',
        ], ['REMOTE_ADDR' => '203.0.113.10'])
        ->assertCreated();

    $this->assertDatabaseHas('user_page_visit_logs', [
        'user_id' => $user->id,
        'page_path' => '/investments',
        'ip_address' => '203.0.113.10',
        'device_type' => 'mobile',
    ]);
});

it('validates page path must start with a slash', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/activity/page-visits', [
            'page_path' => 'dashboard',
        ])
        ->assertUnprocessable();
});

it('returns aggregated page visits for the admin page visits screen', function () {
    $user = User::factory()->create([
        'first_name' => 'Salma',
        'last_name' => 'Ibrahim',
        'email' => 'salma@example.com',
    ]);

    UserPageVisitLog::factory()->count(3)->create([
        'user_id' => $user->id,
        'page_path' => '/dashboard',
        'page_title' => 'Dashboard',
        'device_type' => 'desktop',
        'traffic_source' => 'direct',
        'visited_at' => now()->subHour(),
    ]);

    UserPageVisitLog::factory()->create([
        'user_id' => null,
        'page_path' => '/login',
        'page_title' => 'Sign in',
        'device_type' => 'mobile',
        'traffic_source' => 'organic',
    ]);

    $token = auth('admin')->login(Admin::factory()->create());

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/user-page-visit-logs?page=1&per_page=10&sort_by=newest');

    $response->assertSuccessful()
        ->assertJsonPath('meta.summary.total_visits', 4)
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'path',
                'page_label',
                'visitor_display_name',
                'visitor_username',
                'member_id',
                'visit_count',
                'last_seen_at',
                'device',
                'source_label',
            ]],
            'meta' => ['summary' => ['total_visits', 'unique_visitors', 'today_visits', 'this_week_visits']],
        ]);

    $dashboardRow = collect($response->json('data'))
        ->firstWhere('path', '/dashboard');

    expect($dashboardRow['visit_count'])->toBe(3);
    expect($dashboardRow['visitor_display_name'])->toBe('Salma Ibrahim');
    expect($dashboardRow['visitor_username'])->toBe('salma');
    expect($dashboardRow['member_id'])->toBe($user->id);
    expect($dashboardRow['source_label'])->toBe('Direct');
});

it('searches aggregated page visits by path or page label', function () {
    UserPageVisitLog::factory()->create([
        'page_path' => '/dashboard',
        'page_title' => 'Dashboard',
    ]);
    UserPageVisitLog::factory()->create([
        'page_path' => '/wallet',
        'page_title' => 'Wallet',
    ]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/user-page-visit-logs?search=dashboard')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.path', '/dashboard');
});

it('returns page visit summary metrics overview for an authenticated admin', function () {
    UserPageVisitLog::factory()->count(2)->create(['visited_at' => now()]);
    UserPageVisitLog::factory()->create(['visited_at' => now()->subDays(3)]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/user-page-visit-logs/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.total_visits', 3)
        ->assertJsonPath('data.today_visits', 2);
});

it('lists raw page visit logs for a particular user profile', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    UserPageVisitLog::factory()->count(2)->create(['user_id' => $user->id]);
    UserPageVisitLog::factory()->create(['user_id' => $otherUser->id]);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/users/'.$user->id.'/page-visit-logs')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.filters.user_id', $user->id)
        ->assertJsonStructure(['data' => [['id', 'page_path', 'visited_at']]]);
});

it('returns user page visit log filter options for an authenticated admin', function () {
    UserPageVisitLog::factory()->create(['page_path' => '/dashboard']);
    UserPageVisitLog::factory()->create(['page_path' => '/investments']);

    $token = auth('admin')->login(Admin::factory()->create());

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/user-page-visit-logs/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 2);
});

it('rejects unauthenticated access to admin page visit logs', function () {
    $this->getJson('/api/admin/user-page-visit-logs')->assertUnauthorized();
});
