<?php

use App\Models\Dashcam;
use App\Models\Department;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fleet = Fleet::create(['name' => 'Allowed fleet', 'code' => 'ALLOWED']);
    $this->foreign = Fleet::create(['name' => 'Confidential fleet', 'code' => 'FOREIGN']);
    $this->vehicle = Vehicle::create(['name' => 'Allowed vehicle', 'fleet_id' => $this->fleet->id]);
    $this->foreignVehicle = Vehicle::create(['name' => 'Confidential vehicle', 'fleet_id' => $this->foreign->id]);
    $this->department = Department::create(['name' => 'Own site', 'fleet_id' => $this->fleet->id]);
    $this->foreignDepartment = Department::create(['name' => 'Confidential site', 'fleet_id' => $this->foreign->id]);
    $this->camera = Dashcam::create(['name' => 'Allowed camera', 'imei' => '123456789012345', 'model' => 'JK114', 'vehicle_id' => $this->vehicle->id]);
    $this->foreignCamera = Dashcam::create(['name' => 'Confidential camera', 'imei' => '223456789012346', 'model' => 'JK114', 'vehicle_id' => $this->foreignVehicle->id]);
    $this->admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->actingAs($this->admin);
    config(['listener.token' => str_repeat('test', 12)]);
    Http::fake(['*' => Http::response(['lease_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'ready' => false])]);
});

it('limits every registry list options counts and dashboard to the assigned fleet', function () {
    foreach (['fleets', 'vehicles', 'departments'] as $kind) {
        $this->getJson('/registry/'.$kind.'?fleet_id='.$this->foreign->id)->assertOk()
            ->assertJsonPath('records.total', 1)->assertDontSee('Confidential');
        $this->getJson('/registry/'.$kind.'?search=Confidential')->assertOk()->assertJsonPath('records.total', 0);
    }
    $this->getJson('/dashcams/options?fleet_id='.$this->foreign->id)->assertOk()->assertJsonCount(1, 'fleets')
        ->assertJsonCount(1, 'vehicles')->assertJsonCount(1, 'departments')->assertDontSee('Confidential');
    $this->get('/')->assertOk()->assertSee('Allowed fleet')->assertDontSee('Confidential')->assertDontSee('DEMO-001')
        ->assertSee('dashcam-form-modal')->assertDontSee('id="dashcam-imei"', false)->assertDontSee('id="dashcam-create"', false);
});

it('creates and edits vehicles and departments with implicit fleet and rejects cross fleet writes', function () {
    $id = $this->postJson('/registry/vehicles', ['name' => 'New car', 'department_id' => $this->department->id])->assertCreated()->json('id');
    expect(Vehicle::find($id)->fleet_id)->toBe($this->fleet->id);
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Renamed', 'department_id' => null])->assertOk();
    expect(Vehicle::find($id)->department_id)->toBeNull();
    $this->postJson('/registry/departments', ['name' => 'New site'])->assertCreated();
    $this->patchJson('/registry/departments/'.$this->department->id, ['name' => 'Updated site'])->assertOk();
    foreach (['vehicles' => $this->foreignVehicle, 'departments' => $this->foreignDepartment] as $kind => $record) {
        $this->patchJson('/registry/'.$kind.'/'.$record->id, ['name' => 'Forbidden'])->assertNotFound();
        $this->postJson('/registry/'.$kind, ['name' => 'Forbidden', 'fleet_id' => $this->foreign->id])->assertUnprocessable();
    }
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Bad transfer', 'fleet_id' => $this->foreign->id])->assertUnprocessable();
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Bad site', 'department_id' => $this->foreignDepartment->id])->assertUnprocessable();
    $this->postJson('/registry/fleets', ['name' => 'Forbidden', 'code' => 'NO'])->assertForbidden();
    $this->patchJson('/registry/fleets/'.$this->fleet->id, ['name' => 'Forbidden'])->assertForbidden();
    expect(Vehicle::find($id)->name)->toBe('Renamed');
});

it('projects safe camera data in both JSON and rendered HTML and never searches hidden identifiers', function () {
    $response = $this->getJson('/dashcams?fleet_id='.$this->foreign->id)->assertOk()->assertJsonPath('stats.total', 1)
        ->assertJsonPath('dashcams.total', 1)->assertDontSee('Confidential');
    foreach (['imei', 'terminal_id_2013', 'terminal_id_2019', 'video_terminal_id', 'auth_token', 'last_ip', 'last_protocol', 'protocol_version', 'gps_timezone_minutes'] as $key) {
        expect($response->json('dashcams.data.0'))->not->toHaveKey($key);
    }
    $response->assertDontSee($this->camera->imei)->assertDontSee($this->camera->terminal_id_2013)->assertDontSee('JT808')->assertDontSee('SIM ID');
    $this->getJson('/dashcams?search='.$this->camera->imei)->assertOk()->assertJsonPath('dashcams.total', 0);
    $this->getJson('/map/vehicles')->assertOk()->assertJsonCount(1, 'vehicles')->assertJsonPath('vehicles.0.equipment.id', $this->camera->id)
        ->assertDontSee($this->camera->imei)->assertDontSee('Confidential');
});

it('lets fleet admins manage existing cameras but not provision hardware or alter technical fields', function () {
    $second = Vehicle::create(['name' => 'Second own car', 'fleet_id' => $this->fleet->id]);
    $this->patchJson('/dashcams/'.$this->camera->id, ['name' => 'Renamed camera', 'vehicle_id' => $second->id])->assertOk();
    expect($this->camera->fresh()->vehicle_id)->toBe($second->id);
    $this->patchJson('/dashcams/'.$this->camera->id, ['enabled' => false])->assertOk();
    expect($this->camera->fresh()->enabled)->toBeFalse();
    $this->patchJson('/dashcams/'.$this->foreignCamera->id, ['name' => 'Forbidden'])->assertNotFound();
    $this->patchJson('/dashcams/'.$this->camera->id, ['vehicle_id' => $this->foreignVehicle->id])->assertUnprocessable();
    $this->postJson('/dashcams', ['name' => 'Hardware'])->assertForbidden();
    foreach (['imei' => '999999999999999', 'protocol_version' => '2013', 'communication_id' => '012345678901', 'channels' => 8, 'auth_token' => 'replacement', 'fleet_id' => $this->foreign->id] as $field => $value) {
        $this->patchJson('/dashcams/'.$this->camera->id, [$field => $value])->assertForbidden();
    }
    expect($this->camera->fresh()->imei)->toBe('123456789012345');
});

it('enforces each delegated management permission separately and keeps users out of account administration', function ($permission, $path) {
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => [$permission]]);
    $this->actingAs($user)->getJson($path)->assertOk();
    foreach (['vehicles.manage' => '/registry/vehicles', 'departments.manage' => '/registry/departments', 'dashcams.manage' => '/dashcams'] as $other => $url) {
        if ($other !== $permission) {
            $this->getJson($url)->assertForbidden();
        }
    }
    $this->getJson('/users')->assertForbidden();
    $this->postJson('/users', ['role' => 'admin'])->assertForbidden();
    $user->update(['permissions' => []]);
    $this->getJson($path)->assertForbidden();
})->with([['vehicles.manage', '/registry/vehicles'], ['departments.manage', '/registry/departments'], ['dashcams.manage', '/dashcams']]);

it('lets an admin create normal users with selected management permissions only in their fleet', function () {
    $payload = ['name' => 'Fleet operator', 'email' => 'operator@example.test', 'password' => 'Testing-Only-2026!', 'password_confirmation' => 'Testing-Only-2026!',
        'permissions' => ['vehicles.manage', 'departments.manage', 'dashcams.manage', 'video.view']];
    $this->postJson('/users', $payload)->assertCreated();
    $operator = User::where('email', $payload['email'])->firstOrFail();
    expect($operator->fleet_id)->toBe($this->fleet->id)->and($operator->isSimpleUser())->toBeTrue()->and($operator->permissions)->toEqual($payload['permissions']);
    $this->postJson('/users', [...$payload, 'email' => 'forged@example.test', 'role' => 'admin'])->assertUnprocessable();
    $this->postJson('/users', [...$payload, 'email' => 'forged@example.test', 'fleet_id' => $this->foreign->id])->assertUnprocessable();
    $this->actingAs($operator)->postJson('/registry/vehicles', ['name' => 'Operator vehicle'])->assertCreated();
    $this->getJson('/map/vehicles')->assertForbidden();
});

it('allows scoped live video without granting management and stops renewing revoked permission', function () {
    $user = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id, 'permissions' => ['video.view']]);
    $this->actingAs($user)->getJson('/dashcams')->assertOk();
    $this->patchJson('/dashcams/'.$this->camera->id, ['enabled' => false])->assertForbidden();
    $this->postJson('/dashcams/'.$this->foreignCamera->id.'/live', ['channel' => 1])->assertNotFound();
    $lease = $this->postJson('/dashcams/'.$this->camera->id.'/live', ['channel' => 1])->assertOk()->json('lease_id');
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    $url = '/dashcams/'.$this->camera->id.'/live/'.$lease;
    $this->postJson($url.'/keepalive')->assertOk();
    $user->update(['permissions' => []]);
    $this->postJson($url.'/keepalive')->assertForbidden();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sessions/'.$lease.'/stop'));
    $this->postJson('/dashcams/'.$this->camera->id.'/live', ['channel' => 1])->assertForbidden();
});

it('rejects a lease renewal after its vehicle leaves the fleet', function () {
    $lease = $this->postJson('/dashcams/'.$this->camera->id.'/live', ['channel' => 1])->assertOk()->json('lease_id');
    $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    $this->postJson('/dashcams/'.$this->camera->id.'/live/'.$lease.'/keepalive')->assertOk();
    $this->vehicle->update(['fleet_id' => $this->foreign->id]);
    $this->postJson('/dashcams/'.$this->camera->id.'/live/'.$lease.'/keepalive')->assertForbidden();
    $this->getJson('/dashcams')->assertOk()->assertJsonPath('dashcams.total', 0);
});

it('does not let another browser session renew or stop a video lease', function () {
    $lease = $this->postJson('/dashcams/'.$this->camera->id.'/live', ['channel' => 1])->assertOk()->json('lease_id');
    $original = session()->getId();
    $url = '/dashcams/'.$this->camera->id.'/live/'.$lease;
    $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('z', 40));
    $this->postJson($url.'/keepalive')->assertForbidden();
    $this->postJson($url.'/stop')->assertForbidden();
    $this->withCookie(config('session.cookie'), $original)->postJson($url.'/keepalive')->assertOk();
});

it('revokes existing streams when the platform transfers a vehicle or camera to a different fleet', function ($kind) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    if ($kind === 'vehicle') {
        $this->patchJson('/registry/vehicles/'.$this->vehicle->id, ['name' => $this->vehicle->name, 'fleet_id' => $this->foreign->id])->assertOk();
    } else {
        $this->camera->update(['protocol_version' => '2019']);
        $this->patchJson('/dashcams/'.$this->camera->id, ['name' => $this->camera->name, 'imei' => $this->camera->imei, 'model' => 'JK114', 'transport' => 'TCP', 'protocol_version' => '2019', 'channels' => 2, 'frame_rate' => 15, 'vehicle_id' => $this->foreignVehicle->id])->assertOk();
    }
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/revoke') && $request['device_id'] === $this->camera->id);
})->with(['vehicle', 'camera']);

it('denies management when an assigned fleet is missing or inactive', function ($state) {
    if ($state === 'missing') {
        $this->admin->update(['fleet_id' => null]);
    } else {
        $this->fleet->update(['status' => 'inactive']);
    }
    $this->admin->unsetRelation('fleet');
    foreach (['/registry/vehicles', '/registry/departments', '/dashcams', '/dashcams/options'] as $path) {
        $this->getJson($path)->assertForbidden();
    }
    $this->patchJson('/dashcams/'.$this->camera->id, ['name' => 'Bad'])->assertForbidden();
    $this->postJson('/dashcams/'.$this->camera->id.'/live', ['channel' => 1])->assertForbidden();
})->with(['missing', 'inactive']);
