<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Subscription;

beforeEach(function () {
    $this->withCredentials();
    $this->admin = PlatformAdmin::factory()->create();
    $this->actingAs($this->admin, 'platform_session');
    $this->withHeaders(platformStatefulHeaders());
});

it('lists subscriptions with filters', function () {
    Subscription::factory()->count(2)->create();

    $this->getJson('/api/platform/subscriptions')
        ->assertOk();
});

it('creates and updates subscription', function () {
    $clinic = Clinic::factory()->create();

    $starts = now()->subWeek()->toDateString();
    $renews = now()->addMonth()->toDateString();

    $create = $this->postJson('/api/platform/subscriptions', [
        'clinic_id' => $clinic->id,
        'plan' => 'Starter',
        'amount' => 99.99,
        'starts_at' => $starts,
        'renews_at' => $renews,
        'payment_method' => 'stripe',
    ]);

    $create->assertCreated();
    $id = $create->json('data.id');

    $this->assertDatabaseHas('audit_log', ['action' => 'subscription.created'], 'central');

    $this->putJson("/api/platform/subscriptions/{$id}", [
        'status' => 'canceled',
    ])->assertOk();

    $sub = Subscription::query()->findOrFail($id);
    expect($sub->status)->toBe('canceled');
    expect($sub->canceled_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_log', ['action' => 'subscription.canceled'], 'central');
});

it('rejects subscription for missing clinic', function () {
    $this->postJson('/api/platform/subscriptions', [
        'clinic_id' => 999999,
        'plan' => 'Starter',
        'amount' => 10,
        'starts_at' => now()->toDateString(),
        'renews_at' => now()->addMonth()->toDateString(),
    ])->assertUnprocessable();
});
