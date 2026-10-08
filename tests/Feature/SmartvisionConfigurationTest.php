<?php

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\SmartvisionConfigurationDraft;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($this->actor);
    $this->fleet = Fleet::create(['name' => 'Fleet', 'code' => 'SMART']);
    $this->vehicle = Vehicle::create(['fleet_id' => $this->fleet->id, 'name' => 'SmartVision test']);
    $this->camera = Dashcam::create(['vehicle_id' => $this->vehicle->id, 'name' => 'SmartVision test', 'model' => 'ES500-603', 'imei' => '111111111111111', 'terminal_id_2013' => '011111111111']);
    $this->url = '/dashcams/'.$this->camera->id.'/configuration';
    $this->settings = ['ip' => '62.171.190.15', 'port' => 7808, 'backup_action' => 'remove', 'secondary_action' => 'keep', 'secondary_backup_action' => 'keep', 'telnum' => '011111111111', 'tid' => '0111111', 'manuf' => '12345', 'module' => 'FX', 'provinceid' => 1, 'cityid' => 1, 'carid' => 'TEST01', 'platecolor' => 0];
    Http::preventStrayRequests();
    Http::fake();
    Bus::fake();
});

function smartvisionEnvelope($test): array
{
    $data = $test->getJson($test->url)->assertOk()->json();

    return ['context' => $data['context'], 'revision' => $data['revision'], 'settings' => $test->settings];
}

it('starts unknown and does not invent camera values from registry or examples', function () {
    $this->getJson($this->url)->assertOk()->assertJsonPath('state', 'empty')->assertJsonPath('settings', null)
        ->assertJsonPath('can_send', false)->assertJsonPath('revision', 0)->assertJsonPath('camera.id', $this->camera->id)
        ->assertDontSee('auth_token')->assertDontSee($this->camera->auth_token);
    $this->assertDatabaseCount('smartvision_configuration_drafts', 0);
    Http::assertNothingSent();
});

it('saves all form fields as pending without altering the camera or dispatching anything', function () {
    $before = $this->camera->fresh()->getAttributes();
    $payload = smartvisionEnvelope($this);
    $this->putJson($this->url, $payload)->assertOk()->assertJsonPath('state', 'pending')->assertJsonPath('can_send', false)
        ->assertJsonPath('revision', 1)->assertJsonPath('settings.telnum', '011111111111')
        ->assertJsonPath('settings.tid', '0111111')->assertJsonPath('settings.backup_action', 'remove')
        ->assertJsonPath('settings.ipbak', null)->assertJsonPath('updated_by', $this->actor->name);
    expect($this->camera->fresh()->getAttributes())->toBe($before);
    $this->getJson($this->url)->assertOk()->assertJsonPath('settings.carid', 'TEST01')->assertJsonPath('settings.platecolor', 0);
    expect(SmartvisionConfigurationDraft::first()->updated_by)->toBe($this->actor->id);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

it('keeps camera values distinct from explicit deletion and accepts secondary destinations', function () {
    $this->settings = [...$this->settings, 'backup_action' => 'set', 'ipbak' => 'backup.example.test', 'portbak' => 6608,
        'secondary_action' => 'set', 'ip2' => '192.0.2.2', 'port2' => 7808, 'secondary_backup_action' => 'remove',
        'telnum' => null, 'carid' => null];
    $this->putJson($this->url, smartvisionEnvelope($this))->assertOk()->assertJsonPath('settings.ipbak', 'backup.example.test')
        ->assertJsonPath('settings.portbak', 6608)->assertJsonPath('settings.secondary_backup_action', 'remove')
        ->assertJsonPath('settings.ipbak2', null)->assertJsonPath('settings.telnum', null);
    $this->settings['backup_action'] = 'keep';
    unset($this->settings['ipbak'], $this->settings['portbak']);
    $this->putJson($this->url, smartvisionEnvelope($this))->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('settings.ipbak', null);
});

it('rejects unsafe ambiguous or malformed field values without writing a draft', function ($override, $field) {
    $this->settings = [...$this->settings, ...$override];
    $this->putJson($this->url, smartvisionEnvelope($this))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('smartvision_configuration_drafts', 0);
    Http::assertNothingSent();
})->with([
    [['ip' => 'https://example.test:7808/path'], 'settings.ip'],
    [['ip' => '999.999.999.999'], 'settings.ip'],
    [['port' => 0], 'settings.port'], [['port' => 65536], 'settings.port'],
    [['backup_action' => 'set'], 'settings.ipbak'],
    [['backup_action' => 'keep', 'ipbak' => '192.0.2.2'], 'settings.ipbak'],
    [['backup_action' => 'remove', 'portbak' => 6608], 'settings.portbak'],
    [['secondary_action' => 'other'], 'settings.secondary_action'],
    [['telnum' => '11111111111'], 'settings.telnum'], [['tid' => '12345678'], 'settings.tid'],
    [['manuf' => 'TOOLONG'], 'settings.manuf'], [['provinceid' => -1], 'settings.provinceid'],
    [['cityid' => 65536], 'settings.cityid'], [['platecolor' => 256], 'settings.platecolor'],
    [['auth_token' => 'forged'], 'settings'], [['raw_command' => '8103'], 'settings'],
]);

it('rejects forged state and dispatch flags even for superadmins', function () {
    foreach (['can_send' => true, 'state' => 'applied', 'dashcam_id' => 999, 'send' => true] as $key => $value) {
        $this->putJson($this->url, [...smartvisionEnvelope($this), $key => $value])->assertUnprocessable();
    }
    $this->assertDatabaseCount('smartvision_configuration_drafts', 0);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

it('prevents stale tabs overwriting either an initial or an existing draft', function () {
    $old = smartvisionEnvelope($this);
    $this->putJson($this->url, $old)->assertOk();
    $old['settings']['port'] = 9000;
    $this->putJson($this->url, $old)->assertConflict();
    $new = smartvisionEnvelope($this);
    $this->putJson($this->url, $new)->assertOk()->assertJsonPath('revision', 2);
    $this->putJson($this->url, $new)->assertConflict();
    expect(SmartvisionConfigurationDraft::first()->settings['port'])->toBe(7808);
});

it('invalidates stale identity or assignment but not normal GPS traffic', function () {
    $old = smartvisionEnvelope($this);
    $this->camera->forceFill(['last_seen_at' => now(), 'last_protocol' => '2013'])->save();
    $this->putJson($this->url, $old)->assertOk();
    $old = smartvisionEnvelope($this);
    $this->camera->update(['terminal_id_2013' => '022222222222']);
    $this->putJson($this->url, $old)->assertConflict();
    $this->getJson($this->url)->assertOk()->assertJsonPath('stale', true);
    $this->putJson($this->url, smartvisionEnvelope($this))->assertOk()->assertJsonPath('stale', false);
    $old = smartvisionEnvelope($this);
    $this->camera->update(['vehicle_id' => null]);
    $this->putJson($this->url, $old)->assertConflict();
});

it('denies clients even with dashcam management permission', function ($role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'fleet_id' => $this->fleet->id, 'permissions' => [User::PERMISSION_DASHCAMS_MANAGE]]));
    $this->getJson($this->url)->assertForbidden();
    $this->putJson($this->url, [])->assertForbidden();
})->with(['admin', 'user']);

it('denies guests inactive users and other camera models', function () {
    $this->camera->update(['model' => 'JK114']);
    $this->getJson($this->url)->assertNotFound();
    $this->putJson($this->url, [])->assertNotFound();
    $this->camera->update(['model' => 'ES500-603']);
    $this->actor->update(['status' => 'inactive']);
    $this->getJson($this->url)->assertRedirect('/login');
    $this->app['auth']->forgetGuards();
    $this->getJson($this->url)->assertUnauthorized();
});

it('preserves isolated drafts while hiding the unavailable configuration interface', function () {
    $this->putJson($this->url, smartvisionEnvelope($this))->assertOk();
    $other = Dashcam::create(['name' => 'Other SmartVision', 'imei' => '222222222222222', 'model' => 'ES500-603']);
    Dashcam::create(['name' => 'ESTON', 'imei' => '333333333333333', 'model' => 'JK114']);
    $this->getJson('/dashcams/'.$other->id.'/configuration')->assertOk()->assertJsonPath('settings', null);
    $table = $this->getJson('/dashcams')->assertOk()->json('html');
    expect($table)->not->toContain('data-smartvision-configuration');
    $this->get('/')->assertOk()->assertSee('dashcam-form-modal')
        ->assertDontSee('smartvision-modal')->assertDontSee('smartvision-config')
        ->assertDontSee('smartvision-configuration.js')->assertDontSee('smartvision-configuration.css')
        ->assertDontSee('Enregistrer en attente');
    $this->assertDatabaseCount('smartvision_configuration_drafts', 1);
    $this->getJson($this->url)->assertOk()->assertJsonPath('settings', SmartvisionConfigurationDraft::first()->settings)
        ->assertJsonPath('state', 'pending')->assertJsonPath('can_send', false);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});
