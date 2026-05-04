<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Central\ClinicUsageRecord;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformService;
use App\Models\Central\PlatformSpending;
use Database\Seeders\PlatformServicesSeeder;

beforeEach(function () {
    $this->withCredentials();
    $this->seed(PlatformServicesSeeder::class);

    $this->admin = PlatformAdmin::factory()->create();
    $this->actingAs($this->admin, 'platform_session');
    $this->withHeaders(platformStatefulHeaders());
});

it('lists platform services', function () {
    $this->getJson('/api/platform/services')
        ->assertOk();

    expect(count($this->getJson('/api/platform/services')->json('data')))->toBeGreaterThanOrEqual(6);
});

it('creates updates and deactivates service', function () {
    $create = $this->postJson('/api/platform/services', [
        'key' => 'test_addon',
        'name' => 'Test',
        'type' => 'addon',
        'billing_model' => 'per_unit',
        'unit_label' => 'unit',
        'default_unit_price' => 0.05,
    ]);

    $create->assertCreated();
    $id = $create->json('data.id');

    $this->putJson("/api/platform/services/{$id}", [
        'name' => 'Test Renamed',
        'is_active' => true,
    ])->assertOk();

    $this->deleteJson("/api/platform/services/{$id}")->assertOk();

    $svc = PlatformService::query()->findOrFail($id);
    expect($svc->is_active)->toBeFalse();
});

it('returns usage aggregation for a service', function () {
    $clinic = Clinic::factory()->create();
    $svc = PlatformService::query()->where('key', 'sms')->firstOrFail();

    ClinicUsageRecord::query()->create([
        'clinic_id' => $clinic->id,
        'service_id' => $svc->id,
        'month' => '2026-04',
        'quantity' => 10,
        'unit_cost' => 0.01,
        'total_cost' => 0.10,
    ]);

    $this->getJson("/api/platform/services/{$svc->id}/usage?month=2026-04")
        ->assertOk()
        ->assertJsonPath('data.0.total_cost', 0.1);
});

it('returns profitability for a service', function () {
    $clinic = Clinic::factory()->create();
    $svc = PlatformService::query()->where('key', 'sms')->firstOrFail();

    ClinicUsageRecord::query()->create([
        'clinic_id' => $clinic->id,
        'service_id' => $svc->id,
        'month' => '2026-04',
        'quantity' => 1,
        'unit_cost' => 1,
        'total_cost' => 50,
    ]);

    PlatformSpending::factory()->create([
        'service_id' => $svc->id,
        'month' => '2026-04',
        'amount' => 20,
        'category_id' => null,
    ]);

    $response = $this->getJson("/api/platform/services/{$svc->id}/profitability?month=2026-04");

    $response->assertOk();
    // JSON may decode whole-number floats as ints
    expect((float) $response->json('data.revenue'))->toBe(50.0);
    expect((float) $response->json('data.cost'))->toBe(20.0);
    expect((float) $response->json('data.profit'))->toBe(30.0);
});
