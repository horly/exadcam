<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
    Http::preventStrayRequests();
    $this->fleet = Fleet::create(['name' => 'History fleet', 'code' => 'HISTORY']);
    $this->vehicle = Vehicle::create(['name' => 'History vehicle', 'fleet_id' => $this->fleet->id]);
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subDays(2)])->save();
    $this->camera = Dashcam::create(['name' => 'History camera', 'imei' => '123456789012345', 'vehicle_id' => $this->vehicle->id]);
    $this->camera->forceFill(['vehicle_assigned_at' => now()->subDays(2)])->save();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->url = '/map/vehicles/'.$this->vehicle->id.'/trips?'.http_build_query([
        'source_id' => $this->camera->id, 'from' => '2026-10-05', 'to' => '2026-10-05', 'timezone' => 'Africa/Kinshasa',
    ]);
    $this->point = function ($time, $lon, $speed = 20, $status = 3) {
        DB::table('dashcam_positions')->insert(['dashcam_id' => $this->camera->id, 'recorded_at' => '2026-10-05 '.$time,
            'latitude' => -4.33, 'longitude' => $lon, 'speed' => $speed, 'status' => $status, 'alarm' => 0, 'created_at' => now()]);
    };
});

it('returns an observed chronology with exact endpoints and the requested local times', function () {
    ($this->point)('08:00:00', 15.22);
    ($this->point)('08:01:00', 15.221);
    ($this->point)('08:02:00', 15.222, 0, 2);
    ($this->point)('08:03:00', 15.222, 0, 2);
    ($this->point)('08:04:00', 15.222);
    ($this->point)('08:05:00', 15.223);
    ($this->point)('08:06:00', 15.224);
    $response = $this->getJson($this->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(array_column($response->json('history.items'), 'type'))->toBe(['trip', 'parking', 'trip']);
    $response->assertJsonPath('summary.count', 2)->assertJsonPath('history.parking_seconds', 120)
        ->assertJsonPath('trips.0.start_coordinates', [15.22, -4.33])->assertJsonPath('trips.0.end_coordinates', [15.222, -4.33])
        ->assertJsonPath('trips.0.start_time', '09:00')->assertDontSee('imei')->assertDontSee('auth_token');
    Http::assertNothingSent();
});

it('keeps parking only periods and does not bridge gaps or invent data after the final sample', function () {
    ($this->point)('08:00:00', 15.22, 0, 2);
    ($this->point)('08:02:00', 15.22, 0, 2);
    ($this->point)('09:00:00', 15.22, 0, 2);
    ($this->point)('09:03:00', 15.22, 0, 2);
    $this->getJson($this->url)->assertOk()->assertJsonPath('summary.count', 0)
        ->assertJsonPath('history.parking_count', 2)->assertJsonPath('history.parking_seconds', 300)
        ->assertJsonPath('history.last.end_time', '10:03');
});

it('enforces fleet permission, enabled camera and current assignment boundaries', function () {
    ($this->point)('08:00:00', 15.22);
    ($this->point)('08:02:00', 15.223);
    $other = Fleet::create(['name' => 'Other', 'code' => 'OTHER']);
    $admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $other->id]);
    $this->actingAs($admin)->getJson($this->url)->assertNotFound();
    $admin->update(['fleet_id' => $this->fleet->id]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('summary.count', 1);
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subHour()])->save();
    $this->getJson($this->url)->assertOk()->assertJsonCount(0, 'history.items');
    $this->camera->update(['enabled' => false]);
    $this->getJson($this->url)->assertNotFound();
});

it('rejects invalid dates long periods and forged sources', function () {
    $this->getJson(str_replace('from=2026-10-05', 'from=2026-08-01', $this->url))->assertUnprocessable();
    $this->getJson(str_replace('timezone=Africa%2FKinshasa', 'timezone=invalid', $this->url))->assertUnprocessable();
    $this->getJson(str_replace('source_id='.$this->camera->id, 'source_id=999999', $this->url))->assertNotFound();
});
