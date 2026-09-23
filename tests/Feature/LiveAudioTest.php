<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['listener.token' => str_repeat('test', 12), 'listener.audio_url' => 'http://127.0.0.1:3003']);
    $this->fleet = Fleet::create(['name' => 'Audio fleet', 'code' => 'AUDIO']);
    $this->vehicle = Vehicle::create(['name' => 'Audio vehicle', 'fleet_id' => $this->fleet->id]);
    $this->camera = Dashcam::create(['name' => 'Audio camera', 'imei' => '123456789012345', 'vehicle_id' => $this->vehicle->id]);
    $this->user = User::factory()->create(['role' => 'user', 'status' => 'active', 'fleet_id' => $this->fleet->id, 'permissions' => ['video.view']]);
    $this->lease = '11111111-1111-4111-8111-111111111111';
    $this->grant = null;
    Http::fake(function ($request) {
        if ($request->url() === 'http://127.0.0.1:3003/sessions') {
            $this->grant = $request['grant'];

            return Http::response(['lease_id' => $this->lease, 'socket_path' => '/audio-live/'.$this->lease, 'token' => str_repeat('a', 64), 'sample_rate' => 16000]);
        }

        return Http::response(['status' => 'ready', 'stopped' => true]);
    });
    $this->url = '/dashcams/'.$this->camera->id.'/audio';
});

it('requires video access for listening and a separate permission for speaking', function () {
    $this->postJson($this->url, ['mode' => 'listen'])->assertUnauthorized();
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'listen'])->assertOk()->assertDontSee($this->camera->imei)->assertDontSee('grant');
    $this->postJson($this->url, ['mode' => 'talk'])->assertForbidden();
    $this->user->update(['permissions' => ['audio.talk']]);
    $this->postJson($this->url, ['mode' => 'talk'])->assertForbidden();
    $this->user->update(['permissions' => ['video.view', 'audio.talk']]);
    $this->postJson($this->url, ['mode' => 'talk'])->assertOk();
});

it('restricts admin audio to their active fleet and enabled cameras', function () {
    $admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $other = User::factory()->create(['role' => 'admin']);
    $this->actingAs($other)->postJson($this->url, ['mode' => 'listen'])->assertForbidden();
    $this->actingAs($admin)->postJson($this->url, ['mode' => 'talk'])->assertOk();
    $this->camera->update(['enabled' => false]);
    $this->postJson($this->url, ['mode' => 'talk'])->assertForbidden();
});

it('protects leases from other users and other browser sessions', function () {
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'listen'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertOk();
    $other = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($other)->postJson($this->url.'/'.$this->lease.'/stop')->assertForbidden();
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertForbidden();
    $this->actingAs($this->user);
    $this->withCookie(config('session.cookie'), str_repeat('z', 40));
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertForbidden();
});

it('isolates polling limits from audio start, renewal and emergency stop', function () {
    $this->actingAs($this->user);
    for ($i = 0; $i < 20; $i++) {
        $this->getJson('/dashboard/data')->assertOk();
    }
    $this->getJson('/dashboard/data')->assertStatus(429);
    $this->postJson($this->url, ['mode' => 'listen'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    for ($i = 0; $i < 30; $i++) {
        $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertOk();
    }
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertStatus(429);
    $this->postJson($this->url.'/'.$this->lease.'/stop')->assertOk();
    expect(Cache::has('audio-grant:'.$this->grant))->toBeFalse();
});

it('revokes listening after assignment changes even within the same fleet', function () {
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'listen'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    $new = Vehicle::create(['name' => 'Different vehicle', 'fleet_id' => $this->fleet->id]);
    $this->camera->update(['vehicle_id' => $new->id]);
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertForbidden();
    expect(Cache::has('audio-grant:'.$this->grant))->toBeFalse();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/stop'));
});

it('rechecks microphone permission independently of browser heartbeats', function () {
    $this->user->update(['permissions' => ['video.view', 'audio.talk']]);
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'talk'])->assertOk();
    $body = ['grant' => $this->grant, 'device_id' => $this->camera->id];
    $this->postJson('/api/internal/listener/audio-access', $body)->assertForbidden();
    $this->withToken(config('listener.token'))->postJson('/api/internal/listener/audio-access', $body)->assertOk();
    $this->user->update(['permissions' => ['video.view']]);
    $this->postJson('/api/internal/listener/audio-access', $body)->assertForbidden();
});

it('invalidates a grant when stopped even if the audio service has gone away', function () {
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'listen'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    Http::fake(['*' => Http::response([], 503)]);
    $this->postJson($this->url.'/'.$this->lease.'/stop')->assertOk();
    expect(Cache::has('audio-grant:'.$this->grant))->toBeFalse();
});

it('expires unrenewed audio access and rejects unsupported modes', function () {
    $this->actingAs($this->user)->postJson($this->url, ['mode' => 'broadcast'])->assertUnprocessable();
    $this->postJson($this->url, ['mode' => 'listen'])->assertOk();
    $this->travel(61)->seconds();
    $this->postJson($this->url.'/'.$this->lease.'/keepalive')->assertForbidden();
});

it('shows microphone controls only with the microphone permission', function () {
    $this->actingAs($this->user)->get('/')->assertOk()->assertSee('data-audio-listen', false)->assertDontSee('data-audio-talk', false);
    $this->user->update(['permissions' => ['video.view', 'audio.talk']]);
    $this->get('/')->assertOk()->assertSee('data-audio-talk', false);
});
