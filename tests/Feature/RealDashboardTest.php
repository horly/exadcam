<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 23)->setTime(14, 30));
    $this->fleet = Fleet::create(['name' => 'My fleet', 'code' => 'MY']);
    $this->car = Vehicle::create(['name' => 'My vehicle', 'fleet_id' => $this->fleet->id]);
    $this->car->forceFill(['fleet_assigned_at' => now()->subDays(10)])->save();
    $this->cam = Dashcam::create(['name' => 'Camera', 'imei' => '987654321012345', 'vehicle_id' => $this->car->id, 'model' => 'JK114', 'channels' => 2]);
    $this->cam->forceFill(['vehicle_assigned_at' => now()->subDays(10), 'last_seen_at' => now()])->save();
    $this->admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->position = function ($at, $alarm = 0, $speed = 0, $camera = null, $status = 3) {
        return DB::table('dashcam_positions')->insertGetId(['dashcam_id' => ($camera ?? $this->cam)->id,
            'recorded_at' => $at, 'latitude' => -4.32, 'longitude' => 15.29, 'status' => $status, 'speed' => $speed, 'alarm' => $alarm]);
    };
});

it('uses real scoped counters, choices, vehicle states and GPS aggregates', function () {
    ($this->position)(now()->subSeconds(10), 0, 20);
    ($this->position)(now()->subSeconds(20), 0, 15);
    $otherFleet = Fleet::create(['name' => 'Hidden fleet', 'code' => 'HIDDEN']);
    $otherCar = Vehicle::create(['name' => 'Hidden vehicle', 'fleet_id' => $otherFleet->id]);
    $foreign = Dashcam::create(['name' => 'Hidden camera', 'imei' => '187654321012346', 'vehicle_id' => $otherCar->id]);
    $foreign->forceFill(['last_seen_at' => now()])->save();
    Dashcam::create(['name' => 'Disabled', 'imei' => '287654321012347', 'vehicle_id' => $this->car->id, 'enabled' => false]);
    $response = $this->actingAs($this->admin)->getJson('/dashboard/data')->assertOk()
        ->assertJsonPath('metrics.vehicles', 1)->assertJsonPath('metrics.online', 1)->assertJsonPath('metrics.dashcams', 1)
        ->assertJsonPath('metrics.channels', 2)->assertJsonPath('metrics.fleets', 1)->assertJsonPath('vehicles.0.status', 'En ligne')
        ->assertJsonPath('vehicles.0.speed', 20)->assertJsonPath('video.0.connection', 'online')->assertJsonPath('video.0.status', 'En ligne · En déplacement')
        ->assertDontSee('Hidden')->assertDontSee($this->cam->imei);
    expect(array_sum($response->json('charts.periods.day.online')))->toBe(1);
    expect(array_sum($response->json('charts.periods.day.moving')))->toBe(1);
    expect($response->json('charts.status.series'))->toBe([1,0]);
    $this->get('/')->assertOk()->assertDontSee('DEMO-001')->assertDontSee('Centre vidéo')->assertDontSee('Distraction au volant')
        ->assertSeeInOrder(['id="tracking-module"','id="dashboard-camera-panel"','id="activity-chart-title"'], false);
});

it('shows only rising alarm bits, including after a reset and on equal timestamps', function () {
    ($this->position)(now()->subMinutes(5), 1);
    ($this->position)(now()->subMinutes(4), 1);
    ($this->position)(now()->subMinutes(3), 5);
    ($this->position)(now()->subMinutes(2), 0);
    ($this->position)(now()->subMinutes(2), 1);
    $response = $this->actingAs($this->admin)->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total', 3);
    expect(array_column($response->json('data'), 'title'))->toBe(['SOS', 'Fatigue au volant', 'SOS']);
    $response->assertDontSee($this->cam->imei)->assertDontSee('auth_token');
});

it('does not fabricate a new alert when a continuing alarm crosses the seven day window', function () {
    ($this->position)(now()->subDays(8), 1);
    ($this->position)(now()->subDays(6), 1);
    $this->actingAs($this->admin)->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total', 0);
});

it('paginates real alerts ten at a time without duplicates and clamps removed pages', function () {
    for ($i = 0; $i < 26; $i++) { ($this->position)(now()->subMinutes(30-$i), $i % 2 ? 1 : 0); }
    $one = $this->actingAs($this->admin)->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total', 13)->assertJsonCount(10, 'data')->json();
    $two = $this->getJson('/dashboard/alerts?page=2')->assertOk()->assertJsonCount(3,'data')->json();
    expect(array_intersect(array_column($one['data'],'id'), array_column($two['data'],'id')))->toBeEmpty();
    $this->getJson('/dashboard/alerts?page=999')->assertOk()->assertJsonPath('page', 2);
    $this->getJson('/dashboard/alerts?page=-1')->assertUnprocessable();
});

it('reports actual lost contact, removes it on reconnection and does not flag a never connected camera', function () {
    $this->cam->forceFill(['last_seen_at' => now()->subMinutes(4)])->save();
    Dashcam::create(['name'=>'Pending','imei'=>'887654321012348','vehicle_id'=>$this->car->id]);
    $this->actingAs($this->admin)->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.title','Perte de contact');
    $this->cam->forceFill(['last_seen_at' => now()])->save();
    $this->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total', 0);
});

it('hides previous assignments and rejects invalid GPS samples in historical aggregates', function () {
    ($this->position)(now()->subDays(2), 1, 40);
    $this->cam->forceFill(['vehicle_assigned_at' => now()->subDay()])->save();
    ($this->position)(now()->subHours(3), 0, 40, null, 1);
    ($this->position)(now()->addHour(), 1, 40);
    $response=$this->actingAs($this->admin)->getJson('/dashboard/data')->assertOk();
    expect(array_sum($response->json('charts.periods.week.online')))->toBe(0);
    expect($response->json('metrics.alerts'))->toBe(0);
    $this->cam->forceFill(['vehicle_assigned_at' => now()->subDays(10)])->save();
    $this->car->forceFill(['fleet_assigned_at' => now()->subDay()])->save();
    $this->getJson('/dashboard/alerts')->assertOk()->assertJsonPath('total',0);
});

it('does not reveal GPS or alerts to video only users, inactive fleets or guests', function () {
    ($this->position)(now()->subSeconds(10), 1, 20);
    $video = User::factory()->create(['role'=>'user','fleet_id'=>$this->fleet->id,'permissions'=>['video.view']]);
    $this->actingAs($video)->getJson('/dashboard/data')->assertOk()->assertJsonPath('charts',null)->assertJsonPath('vehicles.0.speed',null)
        ->assertJsonPath('vehicles.0.motion',null)->assertJsonPath('metrics.alerts',0)->assertDontSee($this->cam->imei)->assertDontSee('latitude');
    $this->getJson('/dashboard/alerts')->assertForbidden();
    $this->fleet->update(['status'=>'inactive']);
    $this->getJson('/dashboard/data')->assertOk()->assertJsonCount(0,'vehicles')->assertJsonCount(0,'video');
    auth()->logout();
    $this->getJson('/dashboard/data')->assertUnauthorized();
    $this->getJson('/dashboard/alerts')->assertUnauthorized();
});

it('links dashboard cards to scoped destinations and hides the client dashcam navigation', function () {
    $response = $this->actingAs($this->admin)->get('/')->assertOk();
    $response->assertSee('Véhicules en ligne')->assertSee('Véhicules hors ligne')->assertSee('État des véhicules')
        ->assertDontSee('Dashcams en ligne')->assertDontSee('Dashcams hors ligne')->assertDontSee('data-nav="dashcams"', false)
        ->assertSee('data-dashcams-view="vehicles"', false);
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($doc);
    foreach (['#vehicles', '#map?connection=online', '#map?connection=offline', '#alerts'] as $href) {
        expect($xpath->query('//a[contains(@class,"metric-card-link") and @href="'.$href.'"]')->length)->toBe(1);
    }
    expect($xpath->query('//a[@class="dashboard-widget-link" and @href="#vehicles"]')->length)->toBe(1);
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $response = $this->actingAs($superadmin)->get('/')->assertOk()->assertSee('Dashcams en ligne')->assertSee('Dashcams hors ligne')
        ->assertSee('data-nav="dashcams"', false)->assertSee('data-dashcams-view="dashcams"', false);
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    expect((new DOMXPath($doc))->query('//a[@class="dashboard-widget-link" and @href="#dashcams"]')->length)->toBe(1);
});

it('counts client vehicles once and keeps camera counters for the platform', function () {
    ($this->position)(now()->subSeconds(5));
    $second = Dashcam::create(['name' => 'Second camera', 'imei' => '777654321022345', 'vehicle_id' => $this->car->id]);
    $second->forceFill(['last_seen_at' => now()->subMinutes(10), 'vehicle_assigned_at' => now()->subDay()])->save();
    Vehicle::create(['name' => 'No camera', 'fleet_id' => $this->fleet->id]);
    $pendingCar = Vehicle::create(['name' => 'Pending vehicle', 'fleet_id' => $this->fleet->id]);
    Dashcam::create(['name' => 'Never connected', 'imei' => '777654321012346', 'vehicle_id' => $pendingCar->id]);
    $this->actingAs($this->admin)->getJson('/dashboard/data')->assertOk()
        ->assertJsonPath('metrics.vehicles', 2)->assertJsonPath('metrics.dashcams', 3)
        ->assertJsonPath('metrics.online_vehicles', 1)->assertJsonPath('metrics.offline_vehicles', 1)
        ->assertJsonPath('charts.status.series', [1, 1])->assertJsonPath('charts.labels.dashcams', 'Véhicules');
    $map = collect($this->getJson('/map/vehicles')->assertOk()->json('vehicles'))->whereNotNull('source_id');
    expect($map->where('online', true)->count())->toBe(1)->and($map->where('online', false)->count())->toBe(1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->getJson('/dashboard/data')->assertOk()
        ->assertJsonPath('metrics.online', 1)->assertJsonPath('metrics.offline', 2)->assertJsonPath('charts.status.series', [1, 2])->assertJsonPath('charts.labels.dashcams', 'Dashcams');
});

it('keeps restricted users within their read-only vehicle access without map shortcuts', function () {
    $viewer = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['video.view']]);
    $response = $this->actingAs($viewer)->get('/')->assertOk()->assertSee('Véhicules en ligne')->assertDontSee('data-nav="dashcams"', false)
        ->assertSee('data-dashcams-view="fleet"', false);
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($doc);
    expect($xpath->query('//a[contains(@class,"metric-card-link")]')->length)->toBe(1)
        ->and($xpath->query('//a[contains(@class,"metric-card-link") and @href="#fleet"]')->length)->toBe(1);
    $this->getJson('/map/vehicles')->assertForbidden();
});
