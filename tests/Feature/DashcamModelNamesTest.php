<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DashcamProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['listener.token' => str_repeat('model-test', 5)]);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->fleet = Fleet::create(['name' => 'Model tests', 'code' => 'MODELS']);
    $this->vehicle = Vehicle::create(['name' => 'Vehicle', 'fleet_id' => $this->fleet->id]);
    Http::fake(['*' => Http::response([])]);
});

it('migrates stored labels without touching telemetry identities calibration or custom names', function () {
    $before = [];
    foreach (['JK114', 'ES500-603'] as $index => $old) {
        foreach ([$old, 'My custom camera'] as $offset => $name) {
            $camera = Dashcam::create(['name' => $name, 'model' => $old, 'imei' => '123456789012'.($index + 1).'0'.$offset,
                'vehicle_id' => $this->vehicle->id, 'protocol_version' => '2013', 'gps_timezone_minutes' => 60,
                'normalize_video_timestamps' => $old === 'ES500-603']);
            DB::table('dashcams')->where('id', $camera->id)->update(['model' => $old]);
            $before[$camera->id] = (array) DB::table('dashcams')->find($camera->id);
        }
    }
    $migration = require database_path('migrations/2026_09_28_090000_correct_dashcam_model_names.php');
    $migration->up();
    $migration->up();
    foreach ($before as $id => $original) {
        $expected = $original;
        $expected['model'] = DashcamProfile::canonicalModel($original['model']);
        if ($original['name'] === $original['model']) {
            $expected['name'] = $expected['model'];
        }
        expect((array) DB::table('dashcams')->find($id))->toBe($expected);
        $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => 'id', 'terminal' => (string) $id])
            ->assertOk()->assertJsonPath('model', $original['model']);
    }
    Http::assertNothingSent();
    $migration->down();
    foreach ($before as $id => $original) {
        expect((array) DB::table('dashcams')->find($id))->toBe($original);
    }
});

it('uses corrected models in creation filters lists map dashboard and history', function ($model, $legacy, $fit) {
    $payload = ['model' => $model, 'name' => $legacy, 'imei' => '123456789012345', 'transport' => 'TCP',
        'protocol_version' => '2013', 'vehicle_id' => $this->vehicle->id, 'channels' => 2, 'frame_rate' => 15];
    if ($model === DashcamProfile::SMARTVISION) {
        $payload['communication_id'] = '012345678901';
    }
    $id = $this->postJson('/dashcams', $payload)->assertCreated()->json('id');
    $this->assertDatabaseHas('dashcams', ['id' => $id, 'model' => $model, 'name' => $model]);
    $this->getJson('/dashcams?'.http_build_query(['model' => $model]))->assertOk()
        ->assertJsonPath('dashcams.total', 1)->assertJsonPath('dashcams.data.0.model', $model)->assertJsonPath('dashcams.data.0.name', $model);
    $this->getJson('/dashcams?'.http_build_query(['search' => $model]))->assertOk()->assertJsonPath('dashcams.total', 1);
    $this->getJson('/dashcams?'.http_build_query(['model' => $legacy]))->assertOk()->assertJsonPath('dashcams.total', 1);
    $user = auth()->user();
    $map = app(App\Services\FleetMapService::class)->snapshot($user);
    expect($map['vehicles'][0]['equipment']['model'])->toBe($model)
        ->and($map['vehicles'][0]['equipment']['video_fit'])->toBe($fit);
    $dashboard = app(App\Services\DashboardService::class)->snapshot($user);
    expect($dashboard['video'][0]['model'])->toBe($model)
        ->and($dashboard['video'][0]['video_fit'])->toBe($fit);
    $this->get('/')->assertOk()->assertSee(DashcamProfile::SMARTVISION)->assertSee(DashcamProfile::ESTON);
    $details = app(App\Services\MapDetailsService::class)->get($user, $this->vehicle->id, [
        'source_id' => $id, 'date' => now()->format('Y-m-d'), 'timezone' => 'Africa/Kinshasa',
    ]);
    expect($details['equipment']['model'])->toBe($model)->and($details['equipment']['name'])->toBe($model);
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/resolve', ['kind' => 'id', 'terminal' => (string) $id])
        ->assertOk()->assertJsonPath('model', $legacy);
    Http::assertNothingSent();
})->with([
    [DashcamProfile::ESTON, 'JK114', 'fill'],
    [DashcamProfile::SMARTVISION, 'ES500-603', 'contain'],
]);

it('accepts old forms but stores only the corrected model and keeps the active profile', function ($old, $canonical) {
    $payload = ['model' => $old, 'name' => $old, 'imei' => '123456789012345', 'transport' => 'TCP',
        'protocol_version' => '2013', 'vehicle_id' => $this->vehicle->id, 'channels' => 2, 'frame_rate' => 15];
    if ($old === 'ES500-603') {
        $payload['communication_id'] = '012345678901';
    }
    $id = $this->postJson('/dashcams', $payload)->assertCreated()->json('id');
    $this->assertDatabaseHas('dashcams', ['id' => $id, 'model' => $canonical, 'name' => $canonical]);
    $this->patchJson('/dashcams/'.$id, [...$payload, 'model' => $canonical])->assertOk();
    Http::assertNothingSent();
})->with([['JK114', DashcamProfile::ESTON], ['ES500-603', DashcamProfile::SMARTVISION]]);
