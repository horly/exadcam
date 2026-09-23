<?php

use App\Models\Dashcam;
use App\Models\Department;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->fleet = Fleet::create(['name' => 'Fleet One', 'code' => 'ONE']);
    $this->other = Fleet::create(['name' => 'Fleet Two', 'code' => 'TWO']);
    $this->department = Department::create(['name' => 'Site Nord', 'fleet_id' => $this->fleet->id]);
});

it('requires a fleet for every department and scopes name and optional code uniqueness to it', function () {
    $this->postJson('/registry/departments', ['name' => 'Site'])->assertUnprocessable()->assertJsonValidationErrors('fleet_id');
    $this->postJson('/registry/departments', ['name' => 'Site', 'fleet_id' => 99999])->assertUnprocessable();
    $this->postJson('/registry/departments', ['name' => 'Site Nord', 'fleet_id' => $this->fleet->id])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/registry/departments', ['name' => 'Site Nord', 'fleet_id' => $this->other->id])->assertCreated();
    $id = $this->postJson('/registry/departments', ['name' => 'Région Sud', 'fleet_id' => $this->fleet->id, 'code' => 'SUD'])->assertCreated()->json('id');
    $this->postJson('/registry/departments', ['name' => 'Autre région', 'fleet_id' => $this->fleet->id, 'code' => 'SUD'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/registry/departments/'.$id, ['name' => 'Région Sud', 'fleet_id' => $this->fleet->id, 'code' => 'SUD'])->assertOk();
    expect($this->fleet->departments()->count())->toBe(2);
});

it('allows vehicles without departments while keeping their fleet mandatory', function () {
    $this->postJson('/registry/vehicles', ['name' => 'Missing fleet'])->assertUnprocessable()->assertJsonValidationErrors('fleet_id');
    $id = $this->postJson('/registry/vehicles', ['name' => 'No department', 'fleet_id' => $this->fleet->id])->assertCreated()->json('id');
    expect(Vehicle::find($id)->department_id)->toBeNull();
    $this->postJson('/registry/vehicles', ['name' => 'Explicit empty', 'fleet_id' => $this->fleet->id, 'department_id' => ''])->assertCreated();
});

it('assigns only departments in the vehicles fleet on both creation and update', function () {
    $payload = ['name' => 'Truck', 'fleet_id' => $this->other->id, 'department_id' => $this->department->id];
    $this->postJson('/registry/vehicles', $payload)->assertUnprocessable()->assertJsonValidationErrors('department_id');
    $payload['fleet_id'] = $this->fleet->id;
    $id = $this->postJson('/registry/vehicles', $payload)->assertCreated()->json('id');
    expect(Vehicle::find($id)->department->id)->toBe($this->department->id);
    $this->patchJson('/registry/vehicles/'.$id, [...$payload, 'department_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('department_id');
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Truck', 'fleet_id' => $this->other->id])->assertUnprocessable()->assertJsonValidationErrors('department_id');
    expect(Vehicle::find($id)->fleet_id)->toBe($this->fleet->id);
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Truck', 'fleet_id' => $this->fleet->id])->assertOk();
    expect(Vehicle::find($id)->department_id)->toBe($this->department->id);
    $this->patchJson('/registry/vehicles/'.$id, ['name' => 'Truck', 'fleet_id' => $this->other->id, 'department_id' => null])->assertOk();
    expect(Vehicle::find($id)->department_id)->toBeNull()->and(Vehicle::find($id)->fleet_id)->toBe($this->other->id);
});

it('blocks changing an occupied departments fleet until the vehicles are reassigned', function () {
    $vehicle = Vehicle::create(['name' => 'Truck', 'fleet_id' => $this->fleet->id, 'department_id' => $this->department->id]);
    $camera = Dashcam::create(['name' => 'Camera', 'imei' => '123456789012345', 'vehicle_id' => $vehicle->id]);
    $before = $camera->fresh()->getAttributes();
    $payload = ['name' => 'Site Nord', 'fleet_id' => $this->other->id];
    $this->patchJson('/registry/departments/'.$this->department->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('fleet_id');
    $this->patchJson('/registry/vehicles/'.$vehicle->id, ['name' => 'Truck', 'fleet_id' => $this->fleet->id, 'department_id' => null])->assertOk();
    $this->patchJson('/registry/departments/'.$this->department->id, $payload)->assertOk();
    expect($this->department->fresh()->fleet_id)->toBe($this->other->id)->and($camera->fresh()->getAttributes())->toBe($before);
});

it('enforces the same fleet rule even for direct database writes', function () {
    expect(fn () => Vehicle::create(['name' => 'Invalid direct write', 'fleet_id' => $this->other->id, 'department_id' => $this->department->id]))->toThrow(QueryException::class);
});

it('lists departments and vehicle assignments with searchable fleet context and escaped names', function () {
    $vehicle = Vehicle::create(['name' => 'Truck', 'fleet_id' => $this->fleet->id, 'department_id' => $this->department->id]);
    $this->getJson('/registry/vehicles?search=Site%20Nord')->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('records.data.0.department.name', 'Site Nord');
    $this->getJson('/registry/departments?search=Fleet%20One')->assertOk()->assertJsonPath('records.data.0.vehicles_count', 1);
    $options = $this->getJson('/dashcams/options')->assertOk()->json('departments');
    expect($options)->toHaveCount(1)->and($options[0]['fleet_id'])->toBe($this->fleet->id);
    $this->department->update(['name' => '<script>Site</script>']);
    $html = $this->getJson('/registry/departments')->assertOk()->json('html');
    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
    $this->get('/')->assertOk()->assertSee('registry-departments-modal')->assertSee('registry-vehicle-department');
});

it('denies all department management to ordinary administrators', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->getJson('/registry/departments')->assertForbidden();
    $this->postJson('/registry/departments', [])->assertForbidden();
    $this->patchJson('/registry/departments/'.$this->department->id, [])->assertForbidden();
    $this->get('/')->assertOk()->assertDontSee('registry-departments-modal');
});

it('migrates existing vehicles without inventing departments or changing their fleet', function () {
    $migration = require database_path('migrations/2026_09_22_140000_create_departments_and_assign_vehicles.php');
    $migration->down();
    $vehicle = Vehicle::create(['name' => 'Existing vehicle', 'fleet_id' => $this->fleet->id]);
    $before = $vehicle->getAttributes();
    $migration->up();
    $after = $vehicle->fresh();
    expect($after->department_id)->toBeNull();
    foreach ($before as $field => $value) {
        expect($after->getAttributes()[$field])->toBe($value);
    }
    $this->assertDatabaseCount('departments', 0);
});
