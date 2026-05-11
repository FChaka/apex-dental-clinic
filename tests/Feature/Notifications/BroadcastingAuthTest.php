<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenantFromHeader;
use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

beforeEach(function (): void {
    $this->clinic = createTestTenant('broadcast-auth-test');
    tenancy()->initialize($this->clinic);

    Config::set([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.driver' => 'reverb',
        'broadcasting.connections.reverb.key' => 'broadcast-auth-test-key',
        'broadcasting.connections.reverb.secret' => 'broadcast-auth-secret',
        'broadcasting.connections.reverb.app_id' => 'broadcast-auth-id',
    ]);

    app(BroadcastManager::class)->purge();

    require base_path('routes/channels.php');
});

afterEach(function (): void {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

function broadcastAuthPayload(Clinic $clinic, StaffMember $staff): array
{
    return [
        'socket_id' => '123.456',
        'channel_name' => 'private-'.$clinic->slug.'.staff.'.$staff->id,
    ];
}

it('authorizes private staff channel for matching session staff and tenant header', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    tenancy()->end();

    $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson('http://'.apiHttpHost().'/broadcasting/auth', broadcastAuthPayload($this->clinic, $staff))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('returns 403 when channel staff id does not match session staff', function (): void {
    $self = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $other = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    tenancy()->end();

    $this->actingAs($self, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson('http://'.apiHttpHost().'/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-'.$this->clinic->slug.'.staff.'.$other->id,
        ])
        ->assertForbidden();
});

it('returns 403 when channel tenant slug does not match resolved tenant', function (): void {
    $otherClinic = createTestTenant('broadcast-auth-other');
    tenancy()->initialize($this->clinic);

    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    tenancy()->end();

    try {
        $this->actingAs($staff, 'clinic_session')
            ->withHeaders(clinicStatefulHeaders($this->clinic))
            ->postJson('http://'.apiHttpHost().'/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-'.$otherClinic->slug.'.staff.'.$staff->id,
            ])
            ->assertForbidden();
    } finally {
        dropTenantDatabaseIfExists($otherClinic);
    }
});

it('initializes tenant from host for broadcasting when X-Tenant-Slug is omitted', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    tenancy()->end();

    $this->actingAs($staff, 'clinic_session')
        ->withHeader('Referer', tenantUrl($this->clinic, '/'))
        ->postJson('http://'.$this->clinic->slug.'.apex.test/broadcasting/auth', broadcastAuthPayload($this->clinic, $staff))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('returns 400 when broadcasting host slug disagrees with X-Tenant-Slug', function (): void {
    createTestTenant('other-clinic');
    tenancy()->initialize($this->clinic);

    $symfony = SymfonyRequest::create(
        '/broadcasting/auth',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_HOST' => 'other-clinic.apex.test',
            'REMOTE_ADDR' => '127.0.0.1',
        ]
    );
    $symfony->headers->set('X-Tenant-Slug', $this->clinic->slug);

    $request = Request::createFromBase($symfony);

    /** @var ResolveTenantFromHeader $middleware */
    $middleware = app(ResolveTenantFromHeader::class);
    $response = $middleware->handle($request, fn () => response()->noContent(200));

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode((string) $response->getContent(), true))
        ->toMatchArray(['message' => 'X-Tenant-Slug does not match tenant host.']);
});
