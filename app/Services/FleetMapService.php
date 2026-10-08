<?php

namespace App\Services;

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\FleetAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FleetMapService
{
    private function validPositions(): Builder
    {
        return DB::table('dashcam_positions')
            ->whereRaw('(status & 2) = 2')
            ->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
            ->where(fn ($query) => $query->where('latitude', '!=', 0)->orWhere('longitude', '!=', 0))
            ->where('recorded_at', '<=', now()->addSeconds(30));
    }

    public function snapshot(User $user): array
    {
        return DB::transaction(fn () => $this->buildSnapshot($user));
    }

    private function buildSnapshot(User $user): array
    {
        $visibleFleets = Fleet::visibleTo($user)->when(! $user->isSuperadmin(), fn ($query) => $query->where('status', 'active'));
        $vehicles = Vehicle::whereIn('fleet_id', $visibleFleets->select('id'))
            ->with(['fleet:id,name', 'department:id,name'])->orderBy('name')->get();
        $cameras = Dashcam::whereIn('vehicle_id', $vehicles->modelKeys())->where('enabled', true)
            ->select(['id', 'name', 'imei', 'model', 'channels', 'vehicle_id', 'last_seen_at', 'vehicle_assigned_at'])
            ->selectSub($this->validPositions()->select('id')->whereColumn('dashcam_id', 'dashcams.id')
                ->whereColumn('recorded_at', '>=', 'dashcams.vehicle_assigned_at')
                ->orderByDesc('recorded_at')->orderByDesc('id')->limit(1), 'fix_id')->get();
        $fixes = DB::table('dashcam_positions')->whereIn('id', $cameras->pluck('fix_id')->filter())->get()->keyBy('id');
        $byVehicle = $cameras->groupBy('vehicle_id');
        $selected = $vehicles->mapWithKeys(function (Vehicle $vehicle) use ($byVehicle, $fixes) {
            $available = $byVehicle->get($vehicle->id, collect());
            // One marker per vehicle, using its freshest eligible GPS source.
            $camera = $available->filter(function ($camera) use ($fixes, $vehicle) {
                $fix = $fixes->get($camera->fix_id);

                return $fix && $vehicle->fleet_assigned_at && Carbon::parse($fix->recorded_at)->gte($vehicle->fleet_assigned_at);
            })->sortByDesc(fn ($camera) => $fixes[$camera->fix_id]->recorded_at.'-'.str_pad($camera->id, 12, '0', STR_PAD_LEFT))->first();

            return [$vehicle->id => $camera ?: $available->sortByDesc('last_seen_at')->first()];
        });
        $ranked = $this->validPositions()->whereIn('dashcam_id', $selected->filter()->pluck('id'))
            ->where('recorded_at', '>=', now()->subMinutes(10))
            ->select(['dashcam_id', 'recorded_at', 'latitude', 'longitude', 'speed'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY dashcam_id ORDER BY recorded_at DESC, id DESC) AS position_rank');
        $recent = DB::query()->fromSub($ranked, 'recent')->where('position_rank', '<=', 60)
            ->orderBy('recorded_at')->get()->groupBy('dashcam_id');
        $items = $vehicles->map(function (Vehicle $vehicle) use ($selected, $fixes, $recent, $user) {
            $camera = $selected->get($vehicle->id);
            $fix = $camera ? $fixes->get($camera->fix_id) : null;
            if ($fix && (! $vehicle->fleet_assigned_at || Carbon::parse($fix->recorded_at)->lt($vehicle->fleet_assigned_at))) {
                $fix = null;
            }
            $online = $camera?->last_seen_at?->gte(now()->subMinutes(3)) ?? false;
            $fresh = $fix && Carbon::parse($fix->recorded_at)->gte(now()->subMinutes(3));
            $state = ! $camera ? 'no_camera' : (! $fix ? 'no_position' : (! $online ? 'offline' : (! $fresh ? 'stale' : (! ($fix->status & 1) ? 'parking' : ((float) $fix->speed >= 3 ? 'moving' : 'stopped')))));
            $cutoff = $camera && $vehicle->fleet_assigned_at ? max($camera->vehicle_assigned_at?->toDateTimeString() ?? '9999', $vehicle->fleet_assigned_at->toDateTimeString()) : '9999';
            $positions = $camera ? $recent->get($camera->id, collect())->filter(fn ($point) => $point->recorded_at >= $cutoff && $fix && $point->recorded_at <= $fix->recorded_at) : collect();

            return [
                'id' => $vehicle->id, 'name' => $vehicle->name, 'registration' => $vehicle->registration_number,
                'fleet' => ['id' => $vehicle->fleet_id, 'name' => $vehicle->fleet->name],
                'department' => $vehicle->department ? ['id' => $vehicle->department_id, 'name' => $vehicle->department->name] : null,
                'source_id' => $camera?->id, 'camera_name' => $user->isSuperadmin() ? $camera?->name : null,
                'equipment' => $camera && FleetAccess::dashcams($user) ? ['id' => $camera->id, 'channels' => $camera->channels, 'video_fit' => $camera->videoFit(), ...($user->isSuperadmin() ? ['name' => $camera->name, 'imei' => $camera->imei, 'model' => $camera->model] : [])] : null,
                'details_url' => url('/map/vehicles/'.$vehicle->id.'/details'),
                'trips_url' => url('/map/vehicles/'.$vehicle->id.'/trips'),
                'online' => $online, 'state' => $state, 'last_seen_at' => $camera?->last_seen_at?->toIso8601String(),
                'position' => $fix ? [...$this->point($fix), 'speed' => (float) $fix->speed, 'ignition' => (bool) ($fix->status & 1)] : null,
                'trail' => $state === 'moving' ? $this->continuousTrail($positions->all()) : [],
            ];
        })->values();

        return ['generated_at' => now()->toIso8601String(), 'refresh_seconds' => 10, 'trail_minutes' => 10, 'vehicles' => $items];
    }

    private function point(object $point): array
    {
        return ['lat' => (float) $point->latitude, 'lng' => (float) $point->longitude, 'at' => Carbon::parse($point->recorded_at)->utc()->toIso8601String()];
    }

    private function continuousTrail(array $positions): array
    {
        $trail = [];
        $last = null;
        foreach ($positions as $position) {
            $point = $this->point($position);
            if ($last) {
                $seconds = Carbon::parse($last['at'])->diffInSeconds(Carbon::parse($point['at']), false);
                $distance = $this->distance($last, $point);
                if ($seconds <= 0) {
                    continue;
                }
                if ($seconds > 120 || $distance > 600 || $distance / $seconds > 70) {
                    $trail = [];
                } elseif ($distance < 3) {
                    $last = $point;

                    continue;
                }
            }
            $trail[] = $point;
            $last = $point;
        }
        $result = [];
        $total = 0;
        foreach (array_reverse($trail) as $point) {
            if ($result !== []) {
                $total += $this->distance($point, $result[0]);
            }
            if ($total > 850 || count($result) >= 10) {
                break;
            }
            array_unshift($result, $point);
        }

        return count($result) > 1 ? $result : [];
    }

    private function distance(array $first, array $second): float
    {
        $a = sin(deg2rad($second['lat'] - $first['lat']) / 2) ** 2
            + cos(deg2rad($first['lat'])) * cos(deg2rad($second['lat'])) * sin(deg2rad($second['lng'] - $first['lng']) / 2) ** 2;

        return 6371000 * 2 * asin(min(1, sqrt($a)));
    }
}
