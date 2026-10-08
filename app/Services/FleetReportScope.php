<?php

namespace App\Services;

use App\Models\Department;
use App\Models\FleetReportRun;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\FleetAccess;
use Illuminate\Support\Collection;

class FleetReportScope
{
    public function authorize(User $user): void
    {
        $user->unsetRelation('fleet');
        abort_unless(FleetAccess::allows($user, User::PERMISSION_REPORTS_GENERATE), 403);
    }

    public function options(User $user): array
    {
        $this->authorize($user);
        $fleets = FleetAccess::fleets($user)->orderBy('name')->get(['id', 'name']);

        return [
            'fleets' => $fleets,
            'departments' => Department::whereIn('fleet_id', $fleets->pluck('id'))->orderBy('name')->get(['id', 'name', 'fleet_id']),
            'vehicles' => FleetAccess::scopeRegistry(Vehicle::query(), $user, 'vehicles')->orderBy('name')->get(['id', 'name', 'registration_number', 'fleet_id', 'department_id']),
        ];
    }

    public function vehicles(User $user, array $filters): Collection
    {
        $this->authorize($user);
        $fleets = FleetAccess::fleets($user);
        if ($filters['fleet_id'] ?? null) {
            abort_unless((clone $fleets)->whereKey($filters['fleet_id'])->exists(), 404);
        }
        if ($filters['department_id'] ?? null) {
            abort_unless(Department::whereIn('fleet_id', $fleets->select('id'))->when($filters['fleet_id'] ?? null, fn ($q, $id) => $q->where('fleet_id', $id))->whereKey($filters['department_id'])->exists(), 404);
        }
        $query = FleetAccess::scopeRegistry(Vehicle::query(), $user, 'vehicles')
            ->when($filters['fleet_id'] ?? null, fn ($q, $id) => $q->where('fleet_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id));
        $ids = array_values(array_unique(array_map('intval', $filters['vehicle_ids'] ?? [])));
        if ($ids) {
            abort_unless((clone $query)->whereIn('id', $ids)->count() === count($ids), 404);
            $query->whereIn('id', $ids);
        }
        abort_if((clone $query)->count() > 100, 422, __('reports.limit_vehicles'));

        return $query->with(['fleet:id,name,status', 'department:id,name', 'dashcams' => fn ($q) => $q->where('enabled', true)->orderByDesc('vehicle_assigned_at')->orderByDesc('id')])->orderBy('id')->get();
    }

    public function hash(User $user, Collection $vehicles): string
    {
        return hash('sha256', json_encode([
            $user->id, $user->fleet_id, $user->role->value, $user->status, $user->disabled_at, $user->permissions,
            $vehicles->map(fn ($v) => [$v->id, $v->fleet_id, $v->department_id, $v->fleet_assigned_at, $v->fleet->status,
                $v->dashcams->map(fn ($d) => [$d->id, $d->vehicle_id, $d->vehicle_assigned_at, $d->enabled])->all()])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function checkRun(User $user, FleetReportRun $run): Collection
    {
        $this->authorize($user);
        abort_unless($run->user_id === $user->id, 404);
        abort_if($run->expires_at->isPast(), 410, __('reports.expired'));
        $vehicles = $this->vehicles($user, [...$run->filters, 'vehicle_ids' => $run->vehicle_ids]);
        abort_unless($vehicles->pluck('id')->all() === $run->vehicle_ids && hash_equals($run->scope_hash, $this->hash($user, $vehicles)), 403, __('reports.scope_changed'));

        return $vehicles;
    }
}
