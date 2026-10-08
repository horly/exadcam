<?php

namespace App\Services;

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MapTripHistoryService
{
    public function get(User $user, int $vehicleId, array $input): array
    {
        return DB::transaction(function () use ($user, $vehicleId, $input) {
            $fleets = Fleet::visibleTo($user)->when(! $user->isSuperadmin(), fn ($q) => $q->where('status', 'active'));
            $vehicle = Vehicle::whereIn('fleet_id', $fleets->select('id'))->findOrFail($vehicleId);
            $camera = Dashcam::where('vehicle_id', $vehicle->id)->where('enabled', true)->findOrFail($input['source_id']);
            $from = Carbon::createFromFormat('!Y-m-d', $input['from'], $input['timezone'])->utc();
            $to = Carbon::createFromFormat('!Y-m-d', $input['to'], $input['timezone'])->addDay()->utc()->min(now());
            $fence = $camera->vehicle_assigned_at && $vehicle->fleet_assigned_at
                ? $camera->vehicle_assigned_at->copy()->max($vehicle->fleet_assigned_at) : now()->addDay();
            try {
                $window = app(FleetReportBuilder::class)->history($camera, $fence, $from, $to);
            } catch (\RuntimeException $error) {
                if ($error->getMessage() !== 'too_large') {
                    throw $error;
                }
                abort(422, __('map.trips_period_limit'));
            }
            $trips = [];
            $items = [];
            $colors = ['#009688', '#795548', '#f44336', '#2563eb', '#8b5cf6', '#e69318'];
            foreach ($window['segments'] as $segment) {
                $start = Carbon::parse($segment['start'])->setTimezone($input['timezone']);
                $end = Carbon::parse($segment['end'])->setTimezone($input['timezone']);
                $coord = fn ($p) => [(float) $p['lng'], (float) $p['lat']];
                // EXADCAM has no stored street address here. Never invent one from a district.
                $address = fn ($p) => sprintf('%.6f, %.6f', $p['lat'], $p['lng']);
                $moving = $segment['state'] === 'moving';
                $item = [
                    'id' => 'history-'.count($items), 'type' => $moving ? 'trip' : $segment['state'],
                    'date' => $start->format('d.m.Y'), 'end_date' => $end->format('d.m.Y'),
                    'start_time' => $start->format('H:i'), 'end_time' => $end->format('H:i'),
                    'started_at' => $segment['start'], 'ended_at' => $segment['end'],
                    'duration_seconds' => $segment['duration_seconds'],
                    'start_coordinates' => $coord($segment['start_position']), 'end_coordinates' => $coord($segment['end_position']),
                    'start_address' => $address($segment['start_position']), 'end_address' => $address($segment['end_position']),
                ];
                if ($moving) {
                    $index = count($trips) + 1;
                    $item += ['index' => $index, 'color' => $colors[($index - 1) % count($colors)],
                        'distance_km' => $segment['distance_km'], 'max_speed_kmh' => $segment['max_speed'],
                        'average_speed_kmh' => round($segment['distance_km'] * 3600 / max(1, $segment['duration_seconds']), 1)];
                    $trips[] = $item + ['coordinates' => array_map($coord, $segment['path'])];
                } else {
                    $item += ['address' => $item['start_address'], 'coordinates' => $item['start_coordinates']];
                }
                $items[] = $item;
            }
            $first = $items[0] ?? null;
            $last = $items ? $items[count($items) - 1] : null;

            return ['trips' => $trips, 'history' => ['items' => $items, 'first' => $first, 'last' => $last,
                'parking_count' => count(array_filter($items, fn ($i) => $i['type'] === 'parking')),
                'parking_seconds' => array_sum(array_column(array_filter($items, fn ($i) => $i['type'] === 'parking'), 'duration_seconds')),
                'elapsed_seconds' => $first && $last ? Carbon::parse($last['ended_at'])->timestamp - Carbon::parse($first['started_at'])->timestamp : 0],
                'summary' => ['count' => count($trips), 'distance_km' => round(array_sum(array_column($trips, 'distance_km')), 3),
                    'duration_seconds' => array_sum(array_column($trips, 'duration_seconds'))]];
        });
    }
}
