<?php

namespace App\Services;

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MapDetailsService
{
    public function get(User $user, int $vehicleId, array $input): array
    {
        return DB::transaction(function () use ($user, $vehicleId, $input) {
            $fleets = Fleet::visibleTo($user)->when(! $user->isSuperadmin(), fn ($q) => $q->where('status', 'active'));
            $vehicle = Vehicle::whereIn('fleet_id', $fleets->select('id'))->with(['fleet', 'department'])->findOrFail($vehicleId);
            $camera = Dashcam::where('vehicle_id', $vehicle->id)->where('enabled', true)->findOrFail($input['source_id']);
            $points = null;
            if (empty($input['summary_only'])) {
                $start = Carbon::createFromFormat('!Y-m-d', $input['date'], $input['timezone'])->utc();
                $end = Carbon::createFromFormat('!Y-m-d', $input['date'], $input['timezone'])->addDay()->utc();
                $points = DB::table('dashcam_positions')->where('dashcam_id', $camera->id)
                    ->where('recorded_at', '>=', $start)->where('recorded_at', '<', $end)
                    ->where('recorded_at', '>=', $camera->vehicle_assigned_at ?? now()->addDay())
                    ->where('recorded_at', '>=', $vehicle->fleet_assigned_at ?? now()->addDay())
                    ->where('recorded_at', '<=', now()->addSeconds(30))
                    ->whereRaw('(status & 2) = 2')->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
                    ->where(fn ($q) => $q->where('latitude', '!=', 0)->orWhere('longitude', '!=', 0))
                    ->orderByDesc('recorded_at')->orderByDesc('id')
                    ->paginate(10, ['recorded_at', 'latitude', 'longitude', 'speed', 'status'], 'page', $input['page'] ?? 1);
            }

            return [
                'vehicle' => ['id' => $vehicle->id, 'name' => $vehicle->name, 'registration' => $vehicle->registration_number,
                    'fleet' => $vehicle->fleet->name, 'department' => $vehicle->department?->name],
                'equipment' => $user->isSuperadmin() ? [
                    'name' => $camera->name, 'imei' => $camera->imei, 'model' => $camera->model,
                    'transport' => $camera->transport, 'protocol_version' => $camera->protocol_version,
                    'last_protocol' => $camera->last_protocol, 'channels' => $camera->channels, 'frame_rate' => $camera->frame_rate,
                    'created_at' => $camera->created_at?->toIso8601String(),
                ] : null,
                'last_seen_at' => $camera->last_seen_at?->toIso8601String(),
                ...($points ? [
                'history' => collect($points->items())->map(fn ($p) => [
                    'at' => Carbon::parse($p->recorded_at)->utc()->toIso8601String(),
                    'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'speed' => (float) $p->speed,
                    'state' => ! ($p->status & 1) ? 'parking' : ((float) $p->speed >= 3 ? 'moving' : 'stopped'),
                    'ignition' => (bool) ($p->status & 1),
                ])->all(),
                'page' => $points->currentPage(), 'has_more' => $points->hasMorePages(),
                'per_page' => $points->perPage(), 'total' => $points->total(), 'last_page' => $points->lastPage(),
                'from' => $points->firstItem(), 'to' => $points->lastItem(),
                ] : []),
            ];
        });
    }
}
