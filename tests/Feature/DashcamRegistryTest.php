<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->fleet = Fleet::create(['name' => 'Test fleet', 'code' => 'TEST']);
    $this->vehicle = Vehicle::create(['fleet_id' => $this->fleet->id, 'name' => 'Test vehicle']);
    $this->payload = ['model' => 'JK114', 'name' => 'Test camera', 'imei' => '123456789012345', 'transport' => 'TCP', 'protocol_version' => '2019', 'vehicle_id' => $this->vehicle->id, 'channels' => 2, 'frame_rate' => 15];
    config(['listener.token' => str_repeat('test', 12), 'listener.default_gps_timezone_minutes' => 60]);
    Http::fake(['*' => Http::response([])]);
});

it('provisions an ES camera using its explicit identifier including the leading zero', function () {
    $payload = [...$this->payload, 'model' => 'ES500-603', 'protocol_version' => '2013', 'communication_id' => '012345678901'];
    $this->postJson('/dashcams', $payload)->assertCreated();
    $camera = Dashcam::first();
    expect($camera->terminal_id_2013)->toBe('012345678901')->and($camera->video_terminal_id)->toBe('012345678901')
        ->and($camera->normalize_video_timestamps)->toBeTrue()->and($camera->vehicle->fleet->id)->toBe($this->fleet->id);
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => 'video', 'terminal' => '012345678901'])->assertOk()->assertJsonPath('id', $camera->id);
    $this->getJson('/dashcams')->assertOk()->assertDontSee($camera->auth_token)->assertDontSee('auth_token');
});

it('enforces model transport year and explicit ES identifier on the server', function ($override, $field) {
    $this->postJson('/dashcams', [...$this->payload, ...$override])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('dashcams', 0);
})->with([
    [['model' => 'Other'], 'model'], [['transport' => 'UDP'], 'transport'], [['protocol_version' => '2011'], 'protocol_version'],
    [['model' => 'ES500-603', 'protocol_version' => '2019', 'communication_id' => '012345678901'], 'protocol_version'],
    [['model' => 'ES500-603', 'protocol_version' => '2013'], 'communication_id'],
    [['model' => 'ES500-603', 'protocol_version' => '2013', 'communication_id' => '12345678901'], 'communication_id'],
    [['imei' => '123'], 'imei'], [['vehicle_id' => null], 'vehicle_id'],
]);

it('accepts both JK years and provisions their video identifiers', function ($year, $video) {
    $this->postJson('/dashcams', [...$this->payload, 'protocol_version' => $year])->assertCreated();
    expect(Dashcam::first()->video_terminal_id)->toBe($video)->and(Dashcam::first()->normalize_video_timestamps)->toBeFalse();
})->with([['2013', '456789012345'], ['2019', '00000123456789012345']]);

it('uses the selected vehicles fleet without requiring a duplicate fleet selection', function () {
    $this->postJson('/dashcams', $this->payload)->assertCreated();
    $camera = Dashcam::first();
    expect($camera->vehicle->fleet->id)->toBe($this->fleet->id);
    $other = Fleet::create(['name' => 'Other fleet', 'code' => 'OTHER']);
    $vehicle = Vehicle::create(['name' => 'Other vehicle', 'fleet_id' => $other->id]);
    // A stale or forged fleet input cannot override the actual vehicle relation.
    $this->patchJson('/dashcams/'.$camera->id, [...$this->payload, 'vehicle_id' => $vehicle->id, 'fleet_id' => $this->fleet->id])->assertOk();
    expect($camera->fresh()->vehicle->fleet->id)->toBe($other->id);
    $this->patchJson('/dashcams/'.$camera->id, [...$this->payload, 'vehicle_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    expect($camera->fresh()->vehicle_id)->toBe($vehicle->id);
    $options = $this->getJson('/dashcams/options')->assertOk()->json('vehicles');
    expect(collect($options)->firstWhere('id', $vehicle->id)['fleet']['name'])->toBe('Other fleet');
});

it('preserves working identities secret observed protocol timezone and stream on metadata edits', function () {
    $this->postJson('/dashcams', $this->payload)->assertCreated();
    $camera = Dashcam::first();
    $camera->update(['terminal_id_2013' => '011111111111', 'video_terminal_id' => '022222222222', 'gps_timezone_minutes' => -180]);
    $camera->forceFill(['last_protocol' => '2013'])->save();
    $before = $camera->fresh();
    $otherVehicle = Vehicle::create(['fleet_id' => $this->fleet->id, 'name' => 'Second vehicle']);
    $this->patchJson('/dashcams/'.$camera->id, [...$this->payload, 'name' => 'Renamed', 'vehicle_id' => $otherVehicle->id])->assertOk();
    $after = $camera->fresh();
    foreach (['terminal_id_2013', 'terminal_id_2019', 'video_terminal_id', 'auth_token', 'last_protocol', 'gps_timezone_minutes'] as $field) {
        expect($after->$field)->toBe($before->$field);
    }
    expect($after->vehicle_id)->toBe($otherVehicle->id);
    Http::assertNothingSent();
});

it('changes an ES identifier atomically and prevents duplicates and partial profile bypass', function () {
    $payload = [...$this->payload, 'model' => 'ES500-603', 'protocol_version' => '2013', 'communication_id' => '012345678901'];
    $this->postJson('/dashcams', $payload)->assertCreated();
    $camera = Dashcam::first();
    $this->postJson('/dashcams', [...$payload, 'imei' => '123456789012346'])->assertUnprocessable()->assertJsonValidationErrors('communication_id');
    $this->patchJson('/dashcams/'.$camera->id, ['protocol_version' => '2019'])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$camera->id, ['terminal_id_2013' => '099999999999'])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$camera->id, [...$payload, 'communication_id' => '099999999999'])->assertOk();
    expect($camera->fresh()->video_terminal_id)->toBe('099999999999')->and($camera->fresh()->terminal_id_2013)->toBe('099999999999');
    Http::assertSentCount(2);
});

it('searches associated fleets and vehicles paginates filters and escapes table contents', function () {
    foreach (range(1, 12) as $index) {
        Dashcam::create(['name' => $index === 1 ? '<script>alert(1)</script>' : 'Camera '.$index, 'imei' => str_pad((string) $index, 15, '0', STR_PAD_LEFT), 'model' => 'JK114', 'vehicle_id' => $this->vehicle->id]);
    }
    $this->getJson('/dashcams?search=Test%20fleet&per_page=5&page=2&sort=name&direction=asc')->assertOk()->assertJsonPath('dashcams.total', 12)->assertJsonCount(5, 'dashcams.data');
    $result = $this->getJson('/dashcams?search=alert')->assertOk()->json('html');
    expect($result)->not->toContain('<script>')->toContain('&lt;script&gt;');
    $this->getJson('/dashcams?model=ES500-603')->assertOk()->assertJsonPath('dashcams.total', 0);
    $this->getJson('/dashcams?sort=invalid&per_page=999')->assertOk()->assertJsonPath('dashcams.per_page', 10);
});

it('supports fleet and vehicle creation editing and options with server validation', function () {
    $fleetId = $this->postJson('/registry/fleets', ['name' => 'Operations', 'code' => 'OPS'])->assertCreated()->json('id');
    $vehicleId = $this->postJson('/registry/vehicles', ['name' => 'Truck', 'fleet_id' => $fleetId, 'registration_number' => 'ABC123'])->assertCreated()->json('id');
    $this->postJson('/registry/vehicles', ['name' => 'Duplicate', 'fleet_id' => $fleetId, 'registration_number' => 'ABC123'])->assertUnprocessable();
    $this->patchJson('/registry/vehicles/'.$vehicleId, ['name' => 'Updated truck', 'fleet_id' => $fleetId, 'registration_number' => 'ABC123'])->assertOk();
    $this->getJson('/registry/vehicles?search=Operations')->assertOk()->assertJsonPath('records.total', 1);
    $this->getJson('/registry/fleets')->assertOk()->assertJsonPath('records.total', 2);
    $this->getJson('/dashcams/options')->assertOk()->assertJsonCount(2, 'fleets')->assertJsonCount(2, 'vehicles');
    $this->get('/')->assertOk()->assertSee('dashcam-form-modal')->assertSee('registry-vehicles-modal');
});

it('denies the management endpoints to non-superadmins', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->getJson('/dashcams/options')->assertForbidden();
    foreach (['/registry/fleets', '/registry/vehicles'] as $url) {
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, [])->assertForbidden();
    }
    $this->patchJson('/registry/fleets/'.$this->fleet->id, ['name' => 'Unauthorized'])->assertForbidden();
});

it('migrates commissioned cameras without altering their communication identities or secret', function () {
    $migration = require database_path('migrations/2026_09_22_120000_add_dashcam_profiles_and_vehicles.php');
    $migration->down();
    $camera = Dashcam::create(['name' => 'ES500-603', 'imei' => '123456789012399', 'terminal_id_2013' => '012345678999', 'video_terminal_id' => '012345678999', 'normalize_video_timestamps' => true, 'gps_timezone_minutes' => 60]);
    $camera->forceFill(['last_protocol' => '2013', 'last_seen_at' => now()])->save();
    $before = $camera->fresh()->getAttributes();
    $migration->up();
    $after = $camera->fresh();
    expect($after->model)->toBe('ES500-603')->and($after->protocol_version)->toBe('2013')->and($after->vehicle_id)->toBeNull();
    foreach ($before as $field => $value) {
        expect($after->getAttributes()[$field])->toBe($value);
    }
    $this->getJson('/dashcams')->assertOk()->assertSee('affecter');
});

it('provisions a clock offset so new camera fixes remain visible after assignment', function ($profile) {
    $this->travelTo(now()->startOfSecond());
    $id = $this->postJson('/dashcams', [...$this->payload, ...$profile])->assertCreated()->json('id');
    $resolved = $this->withToken(config('listener.token'))
        ->postJson('/api/internal/listener/resolve', ['kind' => 'id', 'terminal' => (string) $id])
        ->assertOk()->assertJsonPath('gps_timezone_minutes', 60)->json();
    // Reproduce the local clock sent by both commissioned models. A null value
    // previously fell back to UTC+8, placing this fix before vehicle assignment.
    $terminalClock = now()->utc()->addHour();
    $recordedAt = $terminalClock->subMinutes($resolved['gps_timezone_minutes'] ?? 480);
    $this->postJson('/api/internal/listener/event', [
        'device_id' => $id, 'protocol' => $profile['protocol_version'], 'ip' => '127.0.0.1',
        'position' => ['latitude' => -4.305276, 'longitude' => 15.300246, 'speed' => 0,
            'recorded_at' => $recordedAt->toIso8601String(), 'alarm' => 0, 'status' => 7],
    ])->assertOk();
    $this->getJson('/map/vehicles')->assertOk()->assertJsonPath('vehicles.0.state', 'stopped')
        ->assertJsonPath('vehicles.0.position.lat', -4.305276)
        ->assertJsonPath('vehicles.0.position.at', now()->utc()->toIso8601String());
})->with([
    [['model' => 'JK114', 'protocol_version' => '2013']],
    [['model' => 'JK114', 'protocol_version' => '2019']],
    [['model' => 'ES500-603', 'protocol_version' => '2013', 'communication_id' => '012345678901']],
]);

it('persists a configurable zero clock offset without replacing existing camera calibration', function () {
    config(['listener.default_gps_timezone_minutes' => 0]);
    $id = $this->postJson('/dashcams', $this->payload)->assertCreated()->json('id');
    expect(Dashcam::findOrFail($id)->gps_timezone_minutes)->toBe(0);
    config(['listener.default_gps_timezone_minutes' => 60]);
    $this->patchJson('/dashcams/'.$id, [...$this->payload, 'name' => 'Renamed'])->assertOk();
    expect(Dashcam::findOrFail($id)->gps_timezone_minutes)->toBe(0);
    Http::assertNothingSent();
});
