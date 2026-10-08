<?php

use App\Jobs\GenerateFleetReport;
use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\FleetReportRun;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FleetReportBuilder;
use App\Services\FleetReportScope;
use App\Support\DashcamProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-28T14:00:00Z'));
    Queue::fake();
    Storage::fake('local');
    Http::preventStrayRequests();
    $this->fleet = Fleet::create(['name' => 'Own fleet', 'code' => 'OWN']);
    $this->foreign = Fleet::create(['name' => 'Foreign fleet', 'code' => 'FOREIGN']);
    $this->vehicle = Vehicle::create(['name' => 'Hilux', 'registration_number' => 'TEST01', 'fleet_id' => $this->fleet->id]);
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subMonth()])->saveQuietly();
    $this->camera = Dashcam::create(['name' => 'Source', 'imei' => '123456789012345', 'model' => DashcamProfile::SMARTVISION, 'vehicle_id' => $this->vehicle->id, 'enabled' => true, 'channels' => 2]);
    $this->camera->forceFill(['vehicle_assigned_at' => now()->subMonth()])->saveQuietly();
    $this->admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($this->admin);
    $this->filters = ['from' => '2026-09-28', 'to' => '2026-09-28', 'type' => 'summary'];
    $this->point = function ($at, $speed = 30, $status = 3, $alarm = 0, $lat = -4.30, $lng = 15.30, $camera = null) {
        DB::table('dashcam_positions')->insert(['dashcam_id' => $camera ?? $this->camera->id, 'recorded_at' => $at, 'latitude' => $lat, 'longitude' => $lng, 'speed' => $speed, 'status' => $status, 'alarm' => $alarm, 'created_at' => now()]);
    };
    $this->start = function ($filters = []) {
        $id = $this->postJson('/reports/runs', [...$this->filters, ...$filters])->assertAccepted()->json('id');

        return FleetReportRun::findOrFail($id);
    };
    $this->finish = function ($run) {
        (new GenerateFleetReport($run->id))->handle(app(FleetReportScope::class), app(FleetReportBuilder::class));
        expect($run->fresh()->status)->toBe('ready');

        return json_decode(Storage::disk('local')->get($run->resultPath()), true);
    };
});

it('queues a private report without issuing camera commands and exposes only permitted choices', function () {
    Vehicle::create(['name' => 'Secret vehicle', 'fleet_id' => $this->foreign->id]);
    $this->getJson('/reports/options')->assertOk()->assertJsonCount(1, 'vehicles')->assertDontSee('Secret vehicle')->assertDontSee('Foreign fleet');
    $run = ($this->start)();
    Queue::assertPushedOn('reports', GenerateFleetReport::class);
    $this->getJson('/reports/runs/'.$run->id)->assertOk()->assertJsonPath('status', 'queued');
    $this->getJson('/reports/runs/'.$run->id.'/export')->assertConflict();
    ($this->finish)($run);
    $this->getJson('/reports/runs/'.$run->id)->assertOk()->assertJsonPath('per_page', 5)->assertJsonPath('rows.0.quality_label', 'Sans données suffisantes');
    $this->getJson('/reports/runs/'.$run->id.'/export')->assertOk()->assertJsonPath('branding.name', 'EXADCAM')->assertJsonPath('branding.fleet', 'Own fleet')->assertDontSee('imei')->assertDontSee('123456789012345');
    Http::assertNothingSent();
});

it('does not allow report access without permission or across owners and fleets', function () {
    $run = ($this->start)();
    ($this->finish)($run);
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['map.view']]);
    $this->actingAs($user)->getJson('/reports/options')->assertForbidden();
    $this->postJson('/reports/runs', $this->filters)->assertForbidden();
    $this->getJson('/reports/runs/'.$run->id)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin', 'fleet_id' => $this->foreign->id]));
    $this->postJson('/reports/runs', [...$this->filters, 'vehicle_ids' => [$this->vehicle->id]])->assertNotFound();
    $this->postJson('/reports/runs', [...$this->filters, 'fleet_id' => $this->fleet->id])->assertNotFound();
    $this->getJson('/reports/runs/'.$run->id)->assertNotFound();
    $this->getJson('/reports/runs/'.$run->id.'/export')->assertNotFound();
});

it('invalidates already prepared results on reassignment', function () {
    $run = ($this->start)();
    ($this->finish)($run);
    $this->camera->update(['vehicle_id' => null]);
    $this->getJson('/reports/runs/'.$run->id)->assertForbidden();
    $this->getJson('/reports/runs/'.$run->id.'/export')->assertForbidden();
    $this->getJson('/reports/runs/'.$run->id.'/details/trips/0')->assertForbidden();
});

it('rechecks authorization in the worker before publishing', function () {
    $run = ($this->start)();
    $this->fleet->update(['status' => 'inactive']);
    (new GenerateFleetReport($run->id))->handle(app(FleetReportScope::class), app(FleetReportBuilder::class));
    expect($run->fresh()->status)->toBe('failed');
    Storage::disk('local')->assertMissing($run->resultPath());
});

it('keeps missing GPS periods unknown and does not bridge long gaps or invalid jumps', function () {
    ($this->point)('2026-09-28 08:00:00');
    ($this->point)('2026-09-28 08:01:00', 30, 3, 0, -4.301);
    ($this->point)('2026-09-28 09:00:00', 30, 3, 0, -4.302);
    ($this->point)('2026-09-28 09:01:00', 30, 3, 0, -4.303);
    ($this->point)('2026-09-28 09:02:00', 30, 3, 0, -8.3);
    ($this->point)('2026-09-28 09:03:00', 0, 1);
    $data = ($this->finish)(($this->start)());
    expect($data['metrics']['known_seconds'])->toBe(120)
        ->and($data['metrics']['unknown_seconds'])->toBe(53880)
        ->and($data['metrics']['gaps'])->toBe(3)
        ->and($data['metrics']['trips'])->toBe(2)
        ->and($data['metrics']['distance_km'])->toBeGreaterThan(0.22)->toBeLessThan(0.23)
        ->and(count($data['segments']))->toBe(2)
        ->and($data['period']['start'])->toBe('2026-09-27T23:00:00+00:00')
        ->and($data['period']['end'])->toBe('2026-09-28T14:00:00+00:00');
});

it('counts transitions of alarms once and exposes maps and video only with their permissions', function () {
    ($this->point)('2026-09-27 22:59:00', 30, 3, 2);
    ($this->point)('2026-09-28 08:00:00', 30, 3, 2);
    ($this->point)('2026-09-28 08:01:00', 30, 3, 0);
    ($this->point)('2026-09-28 08:02:00', 30, 3, 2);
    ($this->point)('2026-09-28 08:03:00', 30, 3, 2);
    $onlyReports = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['reports.generate']]);
    $this->actingAs($onlyReports);
    $run = ($this->start)();
    $data = ($this->finish)($run);
    expect($data['metrics']['alerts'])->toBe(1);
    $this->getJson('/reports/runs/'.$run->id.'?type=safety')->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.has_map', false)->assertJsonPath('rows.0.has_video', false)->assertJsonPath('rows.0.position_label', '—')->assertDontSee('camera_id');
    $this->getJson('/reports/runs/'.$run->id.'/details/safety/0')->assertOk()->assertJsonPath('path', [])->assertJsonPath('camera_id', null);
    $this->getJson('/reports/runs/'.$run->id.'/export?type=safety')->assertOk()->assertJsonPath('rows.0.position_label', '—');
});

it('excludes history before both assignment dates and all other camera sources', function () {
    $this->vehicle->forceFill(['fleet_assigned_at' => '2026-09-28 08:00:00'])->saveQuietly();
    ($this->point)('2026-09-28 07:59:00', 30, 3, 1);
    ($this->point)('2026-09-28 08:00:00', 30, 3, 0);
    ($this->point)('2026-09-28 08:01:00', 30, 3, 2, -4.301);
    $old = Dashcam::create(['name' => 'Older', 'imei' => '123456789012346', 'model' => DashcamProfile::ESTON, 'vehicle_id' => $this->vehicle->id, 'enabled' => true]);
    $old->forceFill(['vehicle_assigned_at' => now()->subMonths(2)])->saveQuietly();
    ($this->point)('2026-09-28 08:00:00', 100, 3, 1, -4.3, 15.3, $old->id);
    $data = ($this->finish)(($this->start)());
    expect($data['metrics']['valid_points'])->toBe(2)->and($data['metrics']['alerts'])->toBe(1)->and($data['metrics']['period_seconds'])->toBe(21600)->and($data['metrics']['known_seconds'])->toBe(60);
});

it('merges only brief ignition-on stops and ignores duplicate timestamps', function () {
    $step = 0;
    foreach ([['08:00:00', 30, 3], ['08:01:00', 0, 3], ['08:02:00', 30, 3], ['08:03:00', 0, 2], ['08:06:00', 0, 2], ['08:07:00', 30, 3], ['08:08:00', 30, 3]] as [$time,$speed,$status]) {
        ($this->point)('2026-09-28 '.$time, $speed, $status, 0, -4.3 - 0.001 * $step++);
    }
    ($this->point)('2026-09-28 08:08:00', 30, 3, 0, -4.306);
    $data = ($this->finish)(($this->start)());
    expect($data['metrics']['known_seconds'])->toBe(480)->and($data['metrics']['moving_seconds'])->toBe(180)->and($data['metrics']['stopped_seconds'])->toBe(60)->and($data['metrics']['parking_seconds'])->toBe(240)->and($data['metrics']['trips'])->toBe(2)->and($data['metrics']['stops'])->toBe(1)->and($data['segments'][0]['duration_seconds'])->toBe(180);
});

it('paginates five rows and searches and sorts the full dataset', function () {
    for ($i = 1; $i <= 7; $i++) {
        Vehicle::create(['name' => 'Van '.$i, 'fleet_id' => $this->fleet->id]);
    }
    $run = ($this->start)();
    ($this->finish)($run);
    $this->getJson('/reports/runs/'.$run->id)->assertOk()->assertJsonCount(5, 'rows')->assertJsonPath('total', 8)->assertJsonPath('last_page', 2);
    $this->getJson('/reports/runs/'.$run->id.'?page=2')->assertOk()->assertJsonCount(3, 'rows')->assertJsonPath('from', 6);
    $this->getJson('/reports/runs/'.$run->id.'?search=Van&sort=vehicle&direction=desc')->assertOk()->assertJsonPath('total', 7)->assertJsonPath('rows.0.vehicle', 'Van 7');
    $this->getJson('/reports/runs/'.$run->id.'/export?search=Van')->assertOk()->assertJsonCount(7, 'rows');
    $this->getJson('/reports/runs/'.$run->id.'?sort=imei')->assertUnprocessable();
    $this->getJson('/reports/runs/'.$run->id.'?per_page=10000')->assertUnprocessable();
});

it('limits period and concurrent runs before expensive calculation', function () {
    $this->postJson('/reports/runs', [...$this->filters, 'from' => '2026-08-01'])->assertUnprocessable();
    $this->postJson('/reports/runs', [...$this->filters, 'to' => '2026-09-29'])->assertUnprocessable();
    ($this->start)();
    ($this->start)();
    $this->postJson('/reports/runs', $this->filters)->assertTooManyRequests();
});

it('saves rolling filter templates owned only by their author', function () {
    $preset = $this->postJson('/reports/presets', [...$this->filters, 'from' => '2026-09-22', 'name' => 'Weekly review'])->assertOk()->assertJsonPath('filters.days', 7)->assertJsonMissingPath('filters.from')->json('id');
    $this->getJson('/reports/options')->assertOk()->assertJsonCount(1, 'presets');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]));
    $this->deleteJson('/reports/presets/'.$preset)->assertNotFound();
    $this->getJson('/reports/options')->assertOk()->assertJsonCount(0, 'presets');
    $this->actingAs($this->admin)->deleteJson('/reports/presets/'.$preset)->assertOk();
});

it('expires private results and removes files using the cleanup command', function () {
    $run = ($this->start)();
    ($this->finish)($run);
    $this->travel(25)->hours();
    $this->getJson('/reports/runs/'.$run->id)->assertGone();
    $this->artisan('reports:prune')->assertSuccessful();
    expect(FleetReportRun::find($run->id))->toBeNull();
    Storage::disk('local')->assertMissing($run->resultPath());
});

it('does not turn stationary speed spikes into trips or documented movement', function () {
    ($this->point)('2026-09-28 08:00:00', 0, 3);
    ($this->point)('2026-09-28 08:01:00', 8, 3);
    ($this->point)('2026-09-28 08:01:10', 0, 3, 0, -4.30001);
    ($this->point)('2026-09-28 08:02:10', 0, 3, 0, -4.30001);
    $data = ($this->finish)(($this->start)());
    expect($data['metrics']['trips'])->toBe(0)->and($data['metrics']['moving_seconds'])->toBe(0)
        ->and($data['metrics']['known_seconds'])->toBe(120)->and($data['metrics']['active_days'])->toBe(0)
        ->and($data['daily'][0]['known_seconds'])->toBe(120)->and($data['daily'][0]['moving_seconds'])->toBe(0);
});

it('splits intervals at Kinshasa midnight and compares the same elapsed duration', function () {
    ($this->point)('2026-09-27 22:59:00', 30, 3);
    ($this->point)('2026-09-27 23:00:00', 30, 3, 0, -4.301);
    ($this->point)('2026-09-27 23:01:00', 30, 3, 0, -4.302);
    $data = ($this->finish)(($this->start)(['from' => '2026-09-27']));
    expect($data['daily'][0]['moving_seconds'])->toBe(60)->and($data['daily'][1]['moving_seconds'])->toBe(60)
        ->and($data['metrics']['known_seconds'])->toBe(120)
        ->and($data['period']['previous_start'])->toBe('2026-09-24T23:00:00+00:00')
        ->and($data['period']['previous_end'])->toBe('2026-09-26T14:00:00+00:00');
});
