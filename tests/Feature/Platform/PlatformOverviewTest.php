<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSpending;
use App\Models\Central\Subscription;

beforeEach(function () {
    $this->withCredentials();
    $this->admin = PlatformAdmin::factory()->create();
    $this->actingAs($this->admin, 'platform_session');
    $this->withHeaders(platformStatefulHeaders());
});

it('returns overview shape and aggregates', function () {
    $c1 = Clinic::factory()->create(['status' => 'active', 'mrr' => 100, 'seats' => 5]);
    $c2 = Clinic::factory()->create(['status' => 'trial', 'mrr' => 50, 'seats' => 3]);

    Subscription::factory()->for($c1)->create(['status' => 'ok']);
    Subscription::factory()->for($c2)->create(['status' => 'past_due']);

    $month = now()->format('Y-m');
    PlatformSpending::factory()->create(['month' => $month, 'amount' => 25]);

    $this->getJson('/api/platform/overview')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_clinics',
                'active_clinics',
                'trial_clinics',
                'suspended_clinics',
                'total_mrr',
                'total_seats',
                'total_cost_this_month',
                'revenue_vs_cost' => ['revenue', 'cost', 'profit'],
                'recent_clinics',
                'subscription_status_breakdown' => ['ok', 'past_due', 'canceled'],
            ],
            'message',
        ])
        ->assertJsonPath('data.total_clinics', 2)
        ->assertJsonPath('data.active_clinics', 1)
        ->assertJsonPath('data.trial_clinics', 1);
});
