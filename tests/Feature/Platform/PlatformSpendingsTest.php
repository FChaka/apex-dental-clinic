<?php

declare(strict_types=1);

use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformCostCategory;
use App\Models\Central\PlatformService;
use App\Models\Central\PlatformSpending;

beforeEach(function () {
    $this->withCredentials();
    $this->admin = PlatformAdmin::factory()->create();
    $this->actingAs($this->admin, 'platform_session');
    $this->withHeaders(platformStatefulHeaders());
});

it('handles cost categories crud', function () {
    $this->getJson('/api/platform/cost-categories')->assertOk();

    $c = $this->postJson('/api/platform/cost-categories', [
        'key' => 'hosting',
        'name' => 'Hosting',
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/platform/cost-categories/{$c}", [
        'name' => 'Hosting Updated',
    ])->assertOk();
});

it('handles spendings crud', function () {
    $cat = PlatformCostCategory::factory()->create();
    $svc = PlatformService::factory()->create();

    $s = $this->postJson('/api/platform/spendings', [
        'category_id' => $cat->id,
        'service_id' => $svc->id,
        'month' => '2026-04',
        'amount' => 100.50,
        'note' => 'Twilio',
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/platform/spendings/{$s}", [
        'amount' => 120,
    ])->assertOk();

    $this->deleteJson("/api/platform/spendings/{$s}")->assertOk();
});

it('returns spendings summary', function () {
    $cat = PlatformCostCategory::factory()->create(['name' => 'Ops']);

    PlatformSpending::factory()->create([
        'category_id' => $cat->id,
        'service_id' => null,
        'month' => '2026-04',
        'amount' => 80,
    ]);

    $response = $this->getJson('/api/platform/spendings/summary?month=2026-04');

    $response->assertOk();
    expect($response->json('data.months'))->toBeArray();
    expect($response->json('data.months.0.month'))->toBe('2026-04');
    expect((float) $response->json('data.months.0.total'))->toBe(80.0);
});
