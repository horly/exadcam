<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['listener.token' => str_repeat('test', 12)]);
});

it('protects the device registry from non-superadmins', function () {
    $this->getJson('/dashcams')->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/dashcams')->assertForbidden();
});

it('provisions exact aliases and never exposes the terminal authentication secret', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $fleet = Fleet::create(['name' => 'Test', 'code' => 'TEST']);
    $vehicle = Vehicle::create(['fleet_id' => $fleet->id, 'name' => 'Test vehicle']);
    $this->actingAs($admin)->postJson('/dashcams', ['model' => 'JK114', 'transport' => 'TCP', 'protocol_version' => '2013', 'vehicle_id' => $vehicle->id, 'name' => 'Test', 'imei' => '123456789012345', 'channels' => 2, 'frame_rate' => 15])->assertCreated();
    $dashcam = Dashcam::first();
    expect($dashcam->terminal_id_2013)->toBe('456789012345')->and($dashcam->terminal_id_2019)->toBe('00000123456789012345');
    expect(DB::table('dashcams')->value('auth_token'))->not->toBe($dashcam->auth_token);
    $this->getJson('/dashcams')->assertOk()->assertDontSee($dashcam->auth_token)->assertDontSee('auth_token');
    $this->postJson('/dashcams', ['model' => 'JK114', 'transport' => 'TCP', 'protocol_version' => '2013', 'vehicle_id' => $vehicle->id, 'name' => 'Duplicate', 'imei' => '123456789012345', 'channels' => 2, 'frame_rate' => 15])->assertUnprocessable();
});

it('rejects invalid credentials, remote clients and unknown terminal identifiers', function () {
    $url = '/api/internal/listener/resolve';
    $body = ['kind' => '2013', 'terminal' => '456789012345'];
    $this->postJson($url, $body)->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->withToken(config('listener.token'))->postJson($url, $body)->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withToken(config('listener.token'))->postJson($url, $body)->assertNotFound();
});

it('resolves registered devices but denies disabled devices and their telemetry', function () {
    $dashcam = Dashcam::create(['name' => 'Test', 'model' => 'ES500-603', 'imei' => '123456789012345']);
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => '2013', 'terminal' => $dashcam->terminal_id_2013])
        ->assertOk()->assertJsonPath('imei', $dashcam->imei)->assertJsonPath('model', 'ES500-603');
    expect($dashcam->fresh()->last_seen_at)->toBeNull();
    $dashcam->update(['enabled' => false]);
    $this->postJson('/api/internal/listener/resolve', ['kind' => '2013', 'terminal' => $dashcam->terminal_id_2013])->assertNotFound();
    $this->postJson('/api/internal/listener/event', ['device_id' => $dashcam->id, 'protocol' => '2013', 'ip' => '127.0.0.1'])->assertNotFound();
    $this->assertDatabaseCount('dashcam_positions', 0);
});

it('keeps the latest position when delayed telemetry arrives', function () {
    $dashcam = Dashcam::create(['name' => 'Test', 'imei' => '123456789012345']);
    $position = ['latitude' => -4.3, 'longitude' => 15.3, 'speed' => 10, 'recorded_at' => now()->subMinute()->toIso8601String(), 'alarm' => 0, 'status' => 6];
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/event', ['device_id' => $dashcam->id, 'protocol' => '2019', 'ip' => '127.0.0.1', 'position' => $position])->assertOk();
    $position['recorded_at'] = now()->subHour()->toIso8601String();
    $position['latitude'] = -5;
    $this->postJson('/api/internal/listener/event', ['device_id' => $dashcam->id, 'protocol' => '2019', 'ip' => '127.0.0.1', 'position' => $position])->assertOk();
    expect((float) $dashcam->fresh()->latitude)->toBe(-4.3);
    $this->assertDatabaseCount('dashcam_positions', 2);
});

it('requires an authorized browser session for video commands and revokes a device even if Node is down', function () {
    Http::fake(['*' => Http::response([], 503)]);
    $dashcam = Dashcam::create(['name' => 'Test', 'imei' => '123456789012345']);
    $this->actingAs(User::factory()->create(['role' => 'user']))->postJson('/dashcams/'.$dashcam->id.'/live', ['channel' => 1])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->postJson('/dashcams/'.$dashcam->id.'/live', ['channel' => 9])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$dashcam->id, ['enabled' => false])->assertOk();
    expect($dashcam->fresh()->enabled)->toBeFalse();
    $this->postJson('/dashcams/'.$dashcam->id.'/live', ['channel' => 1])->assertForbidden();
    $this->postJson('/dashcams/'.$dashcam->id.'/live/00000000-0000-0000-0000-000000000000/keepalive')->assertForbidden();
});

it('keeps an independent GPS timezone per device and validates changes', function () {
    Http::fake(['*' => Http::response(['disconnected' => true, 'revoked' => true])]);
    $first = Dashcam::create(['name' => 'Local time', 'imei' => '123456789012345', 'gps_timezone_minutes' => 60]);
    $second = Dashcam::create(['name' => 'Default time', 'imei' => '123456789012346']);
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => '2019', 'terminal' => $first->terminal_id_2019])
        ->assertOk()->assertJsonPath('gps_timezone_minutes', 60);
    $this->postJson('/api/internal/listener/resolve', ['kind' => '2019', 'terminal' => $second->terminal_id_2019])
        ->assertOk()->assertJsonPath('gps_timezone_minutes', null);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->patchJson('/dashcams/'.$first->id, ['gps_timezone_minutes' => 900])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$first->id, ['gps_timezone_minutes' => 0])->assertOk();
    expect($first->fresh()->gps_timezone_minutes)->toBe(0);
    expect($second->fresh()->gps_timezone_minutes)->toBeNull();
});

it('accepts only exact short or extended registered video identities', function () {
    Http::fake(['*' => Http::response([])]);
    $dashcam = Dashcam::create(['name' => 'Extended', 'imei' => '123456789012345']);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->patchJson('/dashcams/'.$dashcam->id, ['video_terminal_id' => '123456789012345'])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$dashcam->id, ['video_terminal_id' => '00000123456789012345'])->assertOk();
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => 'video', 'terminal' => '00000123456789012345'])->assertOk()->assertJsonPath('id', $dashcam->id);
    $this->postJson('/api/internal/listener/resolve', ['kind' => 'video', 'terminal' => '456789012345'])->assertNotFound();
});

it('isolates video timestamp normalization to explicitly configured devices', function () {
    Http::fake(['*' => Http::response([])]);
    $first = Dashcam::create(['name' => 'Compatibility mode', 'imei' => '123456789012345']);
    $second = Dashcam::create(['name' => 'Native timing', 'imei' => '123456789012346']);
    $this->actingAs(User::factory()->create(['role' => 'user']))->patchJson('/dashcams/'.$first->id, ['normalize_video_timestamps' => true])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->patchJson('/dashcams/'.$first->id, ['normalize_video_timestamps' => 'invalid'])->assertUnprocessable();
    $this->patchJson('/dashcams/'.$first->id, ['normalize_video_timestamps' => true])->assertOk();
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => 'id', 'terminal' => (string) $first->id])->assertOk()->assertJsonPath('normalize_video_timestamps', true);
    $this->postJson('/api/internal/listener/resolve', ['kind' => 'id', 'terminal' => (string) $second->id])->assertOk()->assertJsonPath('normalize_video_timestamps', false);
});
