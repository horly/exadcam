<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fleet = Fleet::create(['name' => 'Own video fleet', 'code' => 'OWNVIDEO']);
    $this->other = Fleet::create(['name' => 'Private other fleet', 'code' => 'OTHERVIDEO']);
    $this->car = Vehicle::create(['name' => 'Own live vehicle', 'registration_number' => 'LIVE-01', 'fleet_id' => $this->fleet->id]);
    $this->foreignCar = Vehicle::create(['name' => 'Private other vehicle', 'fleet_id' => $this->other->id]);
    $this->camera = Dashcam::create(['name' => 'Own camera', 'imei' => '123456789012345', 'vehicle_id' => $this->car->id, 'model' => 'ES500-603', 'channels' => 2]);
    $this->foreignCamera = Dashcam::create(['name' => 'Private other camera', 'imei' => '223456789012346', 'vehicle_id' => $this->foreignCar->id, 'model' => 'JK114']);
});

it('renders real searchable vehicle choices within the admin fleet without hardware identifiers', function () {
    $user = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $response = $this->actingAs($user)->get('/')->assertOk()
        ->assertSee('dashboard-camera-vehicle')->assertSee('dashboard-channel-1')->assertSee('dashboard-channel-2')
        ->assertSee('LIVE-01')->assertDontSee('Private other')->assertDontSee($this->camera->imei);
    $options = $response->viewData('dashboardVideo')->all();
    expect($options)->toHaveCount(1);
    expect($options[0])->toBe(['id' => $this->car->id, 'label' => 'Own live vehicle · LIVE-01 · Own video fleet',
        'device_id' => $this->camera->id, 'channels' => 2, 'video_fit' => 'contain',
        'status' => 'En attente de connexion', 'connection' => 'pending', 'last_seen_at' => null]);
});

it('permits video-only users without requiring map or management permission', function () {
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['video.view']]);
    $this->actingAs($user)->get('/')->assertOk()->assertSee('dashboard-camera-panel')->assertDontSee('tracking-workspace');
    $this->getJson('/map/vehicles')->assertForbidden();
});

it('omits all camera choices when video permission is missing or the fleet is inactive', function ($inactive) {
    if ($inactive) {
        $this->fleet->update(['status' => 'inactive']);
    }
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => $inactive ? ['video.view'] : ['map.view']]);
    $response = $this->actingAs($user)->get('/')->assertOk()->assertDontSee('id="dashboard-camera-panel"', false);
    expect($response->viewData('dashboardVideo'))->toBeEmpty();
})->with([false, true]);

it('chooses the freshest enabled camera once per vehicle and excludes unassigned devices', function () {
    $this->camera->forceFill(['last_seen_at' => now()->subMinute()])->save();
    $newer = Dashcam::create(['name' => 'Newer own camera', 'imei' => '323456789012347', 'vehicle_id' => $this->car->id, 'model' => 'JK114', 'channels' => 1]);
    $newer->forceFill(['last_seen_at' => now()])->save();
    Dashcam::create(['name' => 'Disabled', 'imei' => '423456789012348', 'vehicle_id' => $this->car->id, 'enabled' => false]);
    Dashcam::create(['name' => 'Unassigned', 'imei' => '523456789012349']);
    $response = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/')->assertOk();
    $choices = $response->viewData('dashboardVideo');
    expect($choices)->toHaveCount(2);
    expect($choices->firstWhere('id', $this->car->id)['device_id'])->toBe($newer->id);
    expect($choices->firstWhere('id', $this->car->id)['channels'])->toBe(1);
});
