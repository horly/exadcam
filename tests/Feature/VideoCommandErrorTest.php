<?php

use App\Models\Dashcam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('distinguishes an explicit device refusal from an unavailable service without opening a lease', function () {
    app()->setLocale('fr');
    config(['listener.token' => str_repeat('test', 12)]);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $camera = Dashcam::create(['name' => 'Test JK114', 'imei' => '123456789012345', 'channels' => 2]);
    Http::fake(['*' => Http::sequence()->push(['code' => 'device_rejected', 'result' => 1], 422)->push([], 503)]);
    $this->postJson('/dashcams/'.$camera->id.'/live', ['channel' => 1])
        ->assertUnprocessable()->assertJsonPath('message', __('dashcams.device_rejected'));
    Http::assertSentCount(1);
    $this->postJson('/dashcams/'.$camera->id.'/live', ['channel' => 1])
        ->assertStatus(503)->assertJsonPath('message', __('dashcams.unavailable'));
});
