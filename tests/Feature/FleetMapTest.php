<?php

use App\Models\Dashcam;
use App\Models\Department;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($this->superadmin);
    $this->fleet = Fleet::create(['name' => 'Real fleet', 'code' => 'REAL']);
    $this->vehicle = Vehicle::create(['name' => 'Real vehicle', 'fleet_id' => $this->fleet->id]);
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subDay()])->save();
    $this->camera = Dashcam::create(['name' => 'Real camera', 'imei' => '123456789012345', 'vehicle_id' => $this->vehicle->id]);
    $this->camera->forceFill(['last_seen_at' => now(), 'vehicle_assigned_at' => now()->subDay()])->save();
    $this->position = function (array $override = []) {
        return DB::table('dashcam_positions')->insertGetId([...[
            'dashcam_id' => $this->camera->id, 'recorded_at' => now()->subSeconds(5),
            'latitude' => -4.334905, 'longitude' => 15.223223, 'speed' => 25, 'alarm' => 0, 'status' => 7, 'created_at' => now(),
        ], ...$override]);
    };
});

it('returns a real position and department without exposing device credentials', function () {
    $department = Department::create(['name' => 'Site Nord', 'fleet_id' => $this->fleet->id]);
    $this->vehicle->update(['department_id' => $department->id]);
    ($this->position)();
    $this->getJson('/map/vehicles')->assertOk()->assertJsonCount(1, 'vehicles')->assertJsonPath('vehicles.0.name', 'Real vehicle')
        ->assertJsonPath('vehicles.0.position.lat', -4.334905)->assertJsonPath('vehicles.0.state', 'moving')
        ->assertJsonPath('vehicles.0.department.name', 'Site Nord')->assertJsonPath('vehicles.0.position.ignition', true)
        ->assertJsonPath('vehicles.0.equipment.imei', '123456789012345')->assertDontSee('auth_token')->assertDontSee('terminal_id')->assertDontSee('last_ip')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('selects the freshest valid source once per vehicle and ignores delayed invalid and future positions', function () {
    ($this->position)();
    ($this->position)(['recorded_at' => now()->subHour(), 'latitude' => -5]);
    ($this->position)(['recorded_at' => now()->subSecond(), 'status' => 0, 'latitude' => -6]);
    ($this->position)(['recorded_at' => now()->addHour(), 'latitude' => -7]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.position.lat', -4.334905);
    $second = Dashcam::create(['name' => 'Second camera', 'imei' => '123456789012346', 'vehicle_id' => $this->vehicle->id]);
    $second->forceFill(['vehicle_assigned_at' => now()->subHour(), 'last_seen_at' => now()])->save();
    ($this->position)(['dashcam_id' => $second->id, 'recorded_at' => now()->subSecond(), 'latitude' => -4.34]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonCount(1, 'vehicles')->assertJsonPath('vehicles.0.source_id', $second->id)->assertJsonPath('vehicles.0.position.lat', -4.34);
});

it('distinguishes stationary offline stale and missing GPS states', function () {
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'no_position')->assertJsonPath('vehicles.0.position', null);
    ($this->position)(['speed' => 0]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'stopped');
    $this->camera->forceFill(['last_seen_at' => now()->subMinutes(4)])->save();
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'offline')->assertJsonPath('vehicles.0.trail', []);
    $this->travel(5)->minutes();
    $this->camera->forceFill(['last_seen_at' => now()])->save();
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'stale');
    $this->camera->update(['enabled' => false]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'no_camera');
});

it('returns only a bounded continuous real movement trail and breaks gaps and GPS jumps', function () {
    foreach (range(0, 19) as $index) {
        ($this->position)(['recorded_at' => now()->subSeconds(200 - $index * 10), 'latitude' => -4.334 + $index * 0.0001]);
    }
    $points = $this->getJson('/map/vehicles')->assertOk()->json('vehicles.0.trail');
    expect($points)->toHaveCount(10)->and($points[0]['lat'])->toBe(-4.333);
    ($this->position)(['recorded_at' => now()->subSecond(), 'latitude' => -5]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.trail', []);
    $this->travel(3)->minutes();
    $this->camera->forceFill(['last_seen_at' => now()])->save();
    ($this->position)(['recorded_at' => now(), 'latitude' => -5.0001]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.trail', []);
});

it('does not draw stationary noise as a route', function () {
    foreach (range(1, 5) as $index) {
        ($this->position)(['recorded_at' => now()->subSeconds(60 - $index * 10), 'speed' => 0]);
    }
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.trail', []);
});

it('applies map permission and fleet boundaries even when clients forge filter parameters', function () {
    ($this->position)();
    $other = Fleet::create(['name' => 'Private other fleet', 'code' => 'OTHER']);
    Vehicle::create(['name' => 'Private other vehicle', 'fleet_id' => $other->id]);
    $admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($admin)->getJson('/map/vehicles?fleet_id='.$other->id)->assertOk()->assertJsonCount(1, 'vehicles')->assertDontSee('Private other')->assertJsonPath('vehicles.0.camera_name', null)->assertJsonPath('vehicles.0.equipment.id', $this->camera->id)->assertJsonMissingPath('vehicles.0.equipment.imei')->assertDontSee('123456789012345');
    $simple = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => []]);
    $this->actingAs($simple)->getJson('/map/vehicles')->assertForbidden();
    $simple->update(['permissions' => [User::PERMISSION_MAP_VIEW]]);
    $this->actingAs($simple)->getJson('/map/vehicles')->assertOk()->assertJsonCount(1, 'vehicles');
    $simple->update(['fleet_id' => null]);
    $this->actingAs($simple)->getJson('/map/vehicles')->assertOk()->assertJsonCount(0, 'vehicles');
    $this->actingAs($admin);
    $this->fleet->update(['status' => 'inactive']);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonCount(0, 'vehicles');
});

it('does not expose previous fleet journeys after reassignment', function () {
    ($this->position)();
    $other = Fleet::create(['name' => 'New fleet', 'code' => 'NEW']);
    $this->vehicle->update(['fleet_id' => $other->id]);
    $admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $other->id]);
    $this->actingAs($admin)->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.position', null)->assertJsonPath('vehicles.0.trail', []);
    $this->travel(10)->seconds();
    ($this->position)();
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'moving');
    $newVehicle = Vehicle::create(['name' => 'Replacement', 'fleet_id' => $other->id]);
    $this->camera->update(['vehicle_id' => $newVehicle->id]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.1.position', null);
});

it('does not reset map assignment boundaries when telemetry or metadata changes', function () {
    $cameraBoundary = $this->camera->vehicle_assigned_at->toIso8601String();
    $vehicleBoundary = $this->vehicle->fleet_assigned_at->toIso8601String();
    $this->camera->update(['name' => 'New name']);
    $this->camera->forceFill(['last_seen_at' => now(), 'latitude' => -4.3])->save();
    $this->vehicle->update(['name' => 'New vehicle name']);
    expect($this->camera->fresh()->vehicle_assigned_at->toIso8601String())->toBe($cameraBoundary)
        ->and($this->vehicle->fresh()->fleet_assigned_at->toIso8601String())->toBe($vehicleBoundary);
});

it('requires authentication for position data', function () {
    auth()->logout();
    $this->getJson('/map/vehicles')->assertUnauthorized();
});

it('uses parking with ignition off and a square at rest with ignition on without drawing noise', function () {
    ($this->position)(['speed' => 0, 'status' => 6]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'parking')->assertJsonPath('vehicles.0.trail', []);
    ($this->position)(['speed' => 0, 'status' => 7, 'recorded_at' => now()]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'stopped');
    ($this->position)(['speed' => 2, 'status' => 6, 'recorded_at' => now(), 'latitude' => -4.334]);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'parking')->assertJsonPath('vehicles.0.trail', []);
});

it('returns paginated device history by local date with an explicit safe equipment projection', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subDays(3)])->save();
    $this->camera->forceFill(['vehicle_assigned_at' => now()->subDays(3)])->save();
    $start = now()->startOfDay()->subHour(); // Midnight in Kinshasa.
    foreach (range(0, 22) as $i) {
        ($this->position)(['recorded_at' => $start->copy()->addMinutes($i)]);
    }
    ($this->position)(['recorded_at' => $start->copy()->subSecond(), 'latitude' => -5]);
    ($this->position)(['status' => 0]);
    $url = '/map/vehicles/'.$this->vehicle->id.'/details?'.http_build_query(['source_id' => $this->camera->id, 'date' => now()->toDateString(), 'timezone' => 'Africa/Kinshasa']);
    $this->getJson($url)->assertOk()->assertJsonCount(10, 'history')->assertJsonPath('has_more', true)
        ->assertJsonPath('total', 23)->assertJsonPath('last_page', 3)->assertJsonPath('per_page', 10)
        ->assertJsonPath('from', 1)->assertJsonPath('to', 10)
        ->assertJsonPath('history.0.at', $start->copy()->addMinutes(22)->toIso8601String())
        ->assertJsonPath('equipment.imei', $this->camera->imei)->assertDontSee('auth_token')->assertDontSee('terminal_id')->assertDontSee('last_ip')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(10, 'history')->assertJsonPath('has_more', true)
        ->assertJsonPath('from', 11)->assertJsonPath('to', 20)->assertJsonPath('total', 23)
        ->assertJsonPath('history.0.at', $start->copy()->addMinutes(12)->toIso8601String());
    $this->getJson($url.'&page=3')->assertOk()->assertJsonCount(3, 'history')->assertJsonPath('has_more', false)
        ->assertJsonPath('from', 21)->assertJsonPath('to', 23)->assertJsonPath('last_page', 3)
        ->assertJsonPath('history.0.at', $start->copy()->addMinutes(2)->toIso8601String());
});

it('scopes history to the permitted fleet and source camera and hides technical data from clients', function () {
    ($this->position)();
    $params = '?'.http_build_query(['source_id' => $this->camera->id, 'date' => now()->toDateString(), 'timezone' => 'UTC']);
    $url = '/map/vehicles/'.$this->vehicle->id.'/details'.$params;
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => [User::PERMISSION_MAP_VIEW]]);
    $this->actingAs($user)->getJson($url)->assertOk()->assertJsonCount(1, 'history')->assertJsonPath('total', 1)->assertJsonPath('equipment', null)->assertDontSee($this->camera->imei);
    $user->update(['permissions' => []]);
    $this->getJson($url)->assertForbidden();
    $user->update(['permissions' => [User::PERMISSION_MAP_VIEW], 'fleet_id' => null]);
    $this->getJson($url)->assertNotFound();
    $this->actingAs($this->superadmin);
    $other = Vehicle::create(['name' => 'Other vehicle', 'fleet_id' => $this->fleet->id]);
    $this->getJson('/map/vehicles/'.$other->id.'/details'.$params)->assertNotFound();
    $this->camera->update(['enabled' => false]);
    $this->getJson($url)->assertNotFound();
});

it('does not reveal history from before assignment and validates history inputs', function () {
    ($this->position)(['recorded_at' => now()->subMinutes(10)]);
    $this->camera->forceFill(['vehicle_assigned_at' => now()->subMinute()])->save();
    $base = '/map/vehicles/'.$this->vehicle->id.'/details';
    $input = ['source_id' => $this->camera->id, 'date' => now()->toDateString(), 'timezone' => 'UTC'];
    $this->getJson($base.'?'.http_build_query($input))->assertOk()->assertJsonCount(0, 'history')
        ->assertJsonPath('total', 0)->assertJsonPath('last_page', 1)->assertJsonPath('from', null)->assertJsonPath('to', null);
    $this->getJson($base.'?'.http_build_query([...$input, 'timezone' => 'Invalid/Zone']))->assertUnprocessable();
    $this->getJson($base.'?'.http_build_query([...$input, 'date' => '2026-99-99']))->assertUnprocessable();
    $this->getJson($base.'?'.http_build_query([...$input, 'page' => 0]))->assertUnprocessable();
    auth()->logout();
    $this->getJson($base.'?'.http_build_query($input))->assertUnauthorized();
});


it('loads the details summary without fetching or displaying the GPS history', function () {
    ($this->position)();
    DB::enableQueryLog();
    $this->getJson('/map/vehicles/'.$this->vehicle->id.'/details?source_id='.$this->camera->id.'&summary_only=1')
        ->assertOk()->assertJsonPath('vehicle.id', $this->vehicle->id)->assertJsonPath('equipment.imei', $this->camera->imei)
        ->assertJsonMissingPath('history')->assertJsonMissingPath('total');
    expect(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'dashcam_positions')))->toBeFalse();
    DB::disableQueryLog();
    $this->get('/')->assertOk()->assertDontSee('id="tracking-history-tab"', false)
        ->assertDontSee('id="tracking-history-date"', false)->assertSee('id="tracking-equipment-fields"', false);
    $client = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => [User::PERMISSION_MAP_VIEW]]);
    $this->actingAs($client)->getJson('/map/vehicles/'.$this->vehicle->id.'/details?source_id='.$this->camera->id.'&summary_only=1')
        ->assertOk()->assertJsonPath('equipment', null)->assertDontSee($this->camera->imei)->assertJsonMissingPath('history');
});
