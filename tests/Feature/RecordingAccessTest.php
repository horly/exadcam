<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DashcamProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(14, 0));
    config(['listener.token' => str_repeat('testing', 8)]);
    $this->fleet = Fleet::create(['name' => 'Allowed', 'code' => 'ALLOWED']);
    $this->foreign = Fleet::create(['name' => 'Foreign', 'code' => 'FOREIGN']);
    $this->vehicle = Vehicle::create(['name' => 'Vehicle', 'fleet_id' => $this->fleet->id]);
    $this->vehicle->forceFill(['fleet_assigned_at' => now()->subDays(2)])->saveQuietly();
    $this->camera = Dashcam::create(['name' => 'Camera', 'imei' => '123456789012345', 'model' => DashcamProfile::SMARTVISION,
        'vehicle_id' => $this->vehicle->id, 'enabled' => true, 'channels' => 2]);
    $this->camera->forceFill(['vehicle_assigned_at' => now()->subDays(2)])->saveQuietly();
    $this->admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($this->admin);
    $this->base = '/dashcams/'.$this->camera->id.'/recordings';
    $this->record = ['channel' => 1, 'start' => '2026-09-28T10:00:00Z', 'end' => '2026-09-28T10:02:00Z',
        'media_type' => 0, 'stream_type' => 0, 'storage_type' => 0, 'size' => 2000000];
    $this->jobId = (string) Str::uuid();
    $this->queryResult = ['records' => [$this->record]];
    $this->queryStatus = 200;
    Http::fake([
        '*/recordings/query' => fn () => Http::response($this->queryResult, $this->queryStatus),
        '*/jobs' => Http::response(['job_id' => $this->jobId, 'status' => 'waiting'], 201),
        '*/jobs/*' => Http::response(['job_id' => $this->jobId, 'status' => 'ready', 'size' => 1000]),
    ]);
    $this->queryRecordings = fn () => $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0])->assertOk()->json('query_id');
    $this->prepareRecording = function () {
        $query = ($this->queryRecordings)();

        return $this->postJson($this->base.'/jobs', ['query_id' => $query, 'index' => 0])->assertCreated()->json('job_id');
    };
});

it('queries both model families and paginates five records without revealing device identifiers', function ($model) {
    $this->camera->update(['model' => $model]);
    $this->queryResult = ['records' => array_fill(0, 23, $this->record)];
    $response = $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 1])->assertOk()
        ->assertJsonPath('total', 23)->assertJsonCount(5, 'records')->assertDontSee('imei')->assertDontSee($this->camera->imei);
    $id = $response->json('query_id');
    $this->getJson($this->base.'/search/'.$id.'?page=5')->assertOk()->assertJsonCount(3, 'records')->assertJsonPath('records.0.index', 20);
    Http::assertSent(fn ($r) => $r['device_id'] === $this->camera->id && $r['channel'] === 1
        && $r['start'] === '2026-09-27T23:00:00+00:00' && $r['end'] === '2026-09-28T14:00:00+00:00');
})->with([DashcamProfile::SMARTVISION, DashcamProfile::ESTON]);

it('does not contact devices for unauthorized or foreign fleet users', function () {
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['map.view']]);
    $this->actingAs($user)->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0])->assertForbidden();
    $foreignAdmin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->foreign->id]);
    $this->actingAs($foreignAdmin)->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0])->assertNotFound();
    $this->actingAs($this->admin);
    $this->camera->update(['enabled' => false]);
    $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0])->assertForbidden();
    Http::assertNothingSent();
});

it('excludes recordings before the current fleet and camera assignments', function () {
    $this->vehicle->forceFill(['fleet_assigned_at' => '2026-09-28 10:00:40'])->saveQuietly();
    $this->camera->forceFill(['vehicle_assigned_at' => '2026-09-28 10:01:00'])->saveQuietly();
    $this->queryResult = ['records' => [$this->record, [...$this->record, 'start' => '2026-09-28T10:01:30Z']]];
    $q = ($this->queryRecordings)();
    $this->getJson($this->base.'/search/'.$q)->assertJsonPath('total', 1)->assertJsonPath('records.0.start', '2026-09-28T10:01:30+00:00');
    $this->postJson($this->base.'/jobs', ['query_id' => $q, 'index' => 0, 'start' => '2026-09-28T10:00:00Z'])->assertUnprocessable();
    $this->postJson($this->base.'/jobs', ['query_id' => $q, 'index' => 0])->assertCreated();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/jobs') && $r['record']['start'] === '2026-09-28T10:01:30+00:00');
    $this->postJson($this->base.'/search', ['date' => '2026-09-27', 'channel' => 0])->assertOk()->assertJsonPath('total', 0);
});

it('cannot reuse another users query or job or use a query after reassignment', function () {
    $q = ($this->queryRecordings)();
    $job = $this->postJson($this->base.'/jobs', ['query_id' => $q, 'index' => 0])->assertCreated()->json('job_id');
    $other = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($other)->getJson($this->base.'/search/'.$q)->assertNotFound();
    $this->postJson($this->base.'/jobs', ['query_id' => $q, 'index' => 0])->assertNotFound();
    foreach (['', '/video'] as $suffix) {
        $this->getJson($this->base.'/jobs/'.$job.$suffix)->assertNotFound();
    }
    $this->postJson($this->base.'/jobs/'.$job.'/cancel')->assertNotFound();
    $this->actingAs($this->admin);
    $this->camera->forceFill(['vehicle_assigned_at' => now()])->saveQuietly();
    $this->postJson($this->base.'/jobs', ['query_id' => $q, 'index' => 0])->assertNotFound();
    $this->getJson($this->base.'/jobs/'.$job.'/video')->assertNotFound();
});

it('rejects forged intervals and out of range indexes without starting a transfer', function () {
    $q = ($this->queryRecordings)();
    foreach ([['index' => 99], ['index' => 0, 'end' => '2026-09-28T11:00:00Z'], ['index' => 0, 'end' => '2026-09-28T09:00:00Z']] as $data) {
        $this->postJson($this->base.'/jobs', ['query_id' => $q, ...$data])->assertStatus($data['index'] === 99 ? 404 : 422);
    }
    $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 8])->assertUnprocessable();
    Http::assertSentCount(1);
});

it('serves authorized ready MP4 with range support and blocks access after permission revocation', function () {
    $job = ($this->prepareRecording)();
    $url = $this->base.'/jobs/'.$job;
    $this->getJson($url.'/video')->assertConflict();
    $this->getJson($url)->assertOk()->assertJsonPath('status', 'ready');
    $root = sys_get_temp_dir().'/exadcam-recording-test-'.Str::uuid();
    mkdir($root.'/'.$job, 0770, true);
    file_put_contents($root.'/'.$job.'/recording.mp4', str_repeat('mp4-test', 100));
    config(['listener.recording_storage' => $root]);
    try {
        $this->get($url.'/video')->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($url.'/video?download=1')->assertDownload('EXADCAM-CH1-20260928-110000.mp4');
        $this->get($url.'/video', ['Range' => 'bytes=0-9'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-9/800');
        $this->admin->update(['role' => 'user', 'permissions' => []]);
        $this->getJson($url.'/video')->assertForbidden();
    } finally {
        unlink($root.'/'.$job.'/recording.mp4');
        rmdir($root.'/'.$job);
        rmdir($root);
    }
});

it('reports offline and unsupported devices without returning fabricated recordings', function ($status) {
    $this->queryResult = ['error' => 'Unavailable'];
    $this->queryStatus = $status;
    $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 1])->assertStatus($status);
})->with([409, 422]);

it('renders recordings only for accounts with video access', function () {
    $this->get('/')->assertOk()->assertSee('id="recordings-module"', false)->assertSee('data-nav="recordings"', false);
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['map.view']]);
    $this->actingAs($user)->get('/')->assertOk()->assertDontSee('id="recordings-module"', false)->assertDontSee('data-nav="recordings"', false);
});

it('filters and sorts the complete cached list while preserving recording identity for playback', function () {
    $this->queryResult = ['records' => array_map(fn ($minute) => [...$this->record,
        'channel' => $minute % 2 + 1,
        'start' => now()->setTime(0, $minute)->toIso8601String(),
        'end' => now()->setTime(0, $minute)->addSeconds(30 + $minute)->toIso8601String(),
        'size' => ($minute + 1) * 1048576,
    ], range(0, 31))];
    $id = ($this->queryRecordings)();
    $this->getJson($this->base.'/search/'.$id.'?per_page=5&page=2&sort=start&direction=asc')
        ->assertOk()->assertJsonCount(5, 'records')->assertJsonPath('records.0.index', 26)
        ->assertJsonPath('from', 6)->assertJsonPath('to', 10)->assertJsonPath('last_page', 7);
    $filtered = $this->getJson($this->base.'/search/'.$id.'?'.http_build_query([
        'per_page' => 5, 'page' => 2, 'search' => __('recordings.channel').' 2', 'sort' => 'duration', 'direction' => 'desc',
    ]))->assertOk()->assertJsonPath('total', 16)->assertJsonPath('unfiltered_total', 32)
        ->assertJsonCount(5, 'records')->assertJsonPath('records.0.index', 10)
        ->assertJsonPath('records.0.duration', 51)->assertJsonPath('records.0.channel', 2)->json('records.0');
    $this->getJson($this->base.'/search/'.$id.'?'.http_build_query(['search' => '28/09/2026 01:21:00']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('records.0.index', 10);
    $this->postJson($this->base.'/jobs', ['query_id' => $id, 'index' => $filtered['index']])->assertCreated();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/jobs') && $r['record']['channel'] === 2
        && $r['record']['start'] === '2026-09-28T00:21:00+00:00' && $r['record']['size'] === 22 * 1048576);
    Http::assertSentCount(2);
});

it('supports page sizes numeric sorting empty results and page bounds without querying the device again', function () {
    $this->queryResult = ['records' => array_map(fn ($size) => [...$this->record, 'size' => $size], range(2, 28))];
    $id = $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0, 'per_page' => 25])
        ->assertOk()->assertJsonCount(25, 'records')->assertJsonPath('per_page', 25)->json('query_id');
    $this->getJson($this->base.'/search/'.$id.'?sort=size&direction=desc&per_page=5&page=99')
        ->assertOk()->assertJsonPath('page', 6)->assertJsonPath('from', 26)->assertJsonPath('to', 27)
        ->assertJsonCount(2, 'records')->assertJsonPath('records.0.size', 3);
    $this->getJson($this->base.'/search/'.$id.'?per_page=50')->assertOk()->assertJsonCount(27, 'records')->assertJsonPath('last_page', 1);
    $this->getJson($this->base.'/search/'.$id.'?search=introuvable&page=99')->assertOk()
        ->assertJsonPath('total', 0)->assertJsonPath('unfiltered_total', 27)->assertJsonPath('from', 0)
        ->assertJsonPath('to', 0)->assertJsonPath('page', 1)->assertJsonPath('last_page', 1)->assertJsonCount(0, 'records');
    Http::assertSentCount(1);
});

it('validates table parameters before querying the camera', function () {
    foreach ([['page' => 0], ['per_page' => 1000], ['search' => ['bad']], ['search' => str_repeat('x', 101)],
        ['sort' => 'imei'], ['direction' => 'invalid']] as $invalid) {
        $this->postJson($this->base.'/search', ['date' => '2026-09-28', 'channel' => 0, ...$invalid])->assertUnprocessable();
    }
    Http::assertNothingSent();
});
