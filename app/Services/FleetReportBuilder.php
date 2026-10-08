<?php

namespace App\Services;

use App\Models\Dashcam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetReportBuilder
{
    public const GAP_SECONDS = 300;

    public const STOP_SECONDS = 180;

    private int $read = 0;

    private float $deadline;

    public function build(Collection $vehicles, array $filters, Carbon $generatedAt): array
    {
        $this->read = 0;
        $this->deadline = microtime(true) + 55;
        $start = Carbon::createFromFormat('!Y-m-d', $filters['from'], 'Africa/Kinshasa')->utc();
        $calendarEnd = Carbon::createFromFormat('!Y-m-d', $filters['to'], 'Africa/Kinshasa')->addDay()->utc();
        $end = $calendarEnd->copy()->min($generatedAt);
        $days = (int) $start->diffInDays($calendarEnd);
        $previousStart = $start->copy()->subDays($days);
        $previousEnd = $previousStart->copy()->addSeconds(max(0, $start->diffInSeconds($end)));
        $result = ['period' => ['from' => $filters['from'], 'to' => $filters['to'], 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'previous_start' => $previousStart->toIso8601String(), 'previous_end' => $previousEnd->toIso8601String(), 'generated_at' => $generatedAt->toIso8601String(), 'timezone' => 'Africa/Kinshasa'],
            'vehicles' => [], 'segments' => [], 'events' => [], 'daily' => [], 'metrics' => $this->empty(), 'previous' => $this->empty()];
        foreach ($vehicles as $vehicle) {
            // A fixed source per vehicle avoids counting the same journey twice.
            $camera = $vehicle->dashcams->first();
            $fence = $camera?->vehicle_assigned_at && $vehicle->fleet_assigned_at
                ? $camera->vehicle_assigned_at->copy()->max($vehicle->fleet_assigned_at) : null;
            $info = ['vehicle_id' => $vehicle->id, 'camera_id' => $camera?->id, 'vehicle' => $vehicle->name, 'registration' => $vehicle->registration_number,
                'fleet' => $vehicle->fleet->name, 'department' => $vehicle->department?->name];
            $current = $this->window($camera, $fence, $start, $end, true);
            $previous = $this->window($camera, $fence, $previousStart, $previousEnd, false);
            $result['vehicles'][] = [...$info, ...$current['totals'], 'coverage' => $this->coverage($current['totals']),
                'quality' => $current['totals']['known_seconds'] === 0 ? 'no_data' : ($current['totals']['unknown_seconds'] > 0 ? 'incomplete' : 'observed'),
                'source_available' => (bool) $camera, 'first_at' => $current['first_at'], 'last_at' => $current['last_at']];
            foreach (['metrics' => $current, 'previous' => $previous] as $key => $window) {
                foreach ($window['totals'] as $name => $value) {
                    $result[$key][$name] = $name === 'max_speed' ? max($result[$key][$name], $value) : $result[$key][$name] + $value;
                }
            }
            foreach ($current['daily'] as $day => $values) {
                $result['daily'][$day] ??= ['day' => $day, 'distance_km' => 0, 'moving_seconds' => 0, 'alerts' => 0, 'known_seconds' => 0];
                foreach ($values as $key => $value) {
                    $result['daily'][$day][$key] += $value;
                }
            }
            foreach ($current['segments'] as $segment) {
                $result['segments'][] = [...$info, ...$segment, 'id' => count($result['segments'])];
            }
            foreach ($current['events'] as $event) {
                $result['events'][] = [...$info, ...$event, 'id' => count($result['events'])];
            }
            if (count($result['segments']) + count($result['events']) > 20000) {
                throw new \RuntimeException('too_large');
            }
        }
        foreach (['metrics', 'previous'] as $key) {
            $result[$key]['coverage'] = $this->coverage($result[$key]);
            $result[$key]['distance_km'] = round($result[$key]['distance_km'], 3);
        }
        $result['metrics']['vehicles'] = count($result['vehicles']);
        $result['metrics']['used_vehicles'] = count(array_filter($result['vehicles'], fn ($v) => $v['active_days'] > 0));
        $result['metrics']['vehicles_with_data'] = count(array_filter($result['vehicles'], fn ($v) => $v['valid_points'] > 0));
        for ($date = $start->copy()->timezone('Africa/Kinshasa'); $date->lt($end); $date->addDay()) {
            $day = $date->format('Y-m-d');
            $result['daily'][$day] ??= ['day' => $day, 'distance_km' => 0, 'moving_seconds' => 0, 'alerts' => 0, 'known_seconds' => 0];
        }
        ksort($result['daily']);
        $result['daily'] = array_values($result['daily']);
        $result['method'] = ['version' => 2, 'max_gap_seconds' => self::GAP_SECONDS, 'stop_seconds' => self::STOP_SECONDS, 'min_trip_seconds' => 60, 'min_trip_km' => 0.1, 'min_trip_extent_km' => 0.05, 'distance' => 'gps_estimate', 'acc' => 'ignition_only'];

        return $result;
    }

    /** A single observed window for the map; no comparison period or external lookup. */
    public function history(Dashcam $camera, Carbon $fence, Carbon $from, Carbon $to): array
    {
        $this->read = 0;
        $this->deadline = microtime(true) + 20;

        return $this->window($camera, $fence, $from, $to, true, 60);
    }

    private function empty(): array
    {
        return ['distance_km' => 0.0, 'moving_seconds' => 0, 'stopped_seconds' => 0, 'parking_seconds' => 0,
            'known_seconds' => 0, 'unknown_seconds' => 0, 'period_seconds' => 0, 'max_speed' => 0.0,
            'valid_points' => 0, 'invalid_points' => 0, 'gaps' => 0, 'alerts' => 0, 'active_days' => 0, 'trips' => 0, 'stops' => 0];
    }

    private function coverage(array $totals): ?float
    {
        return $totals['period_seconds'] ? round(100 * $totals['known_seconds'] / $totals['period_seconds'], 1) : null;
    }

    private function valid(object $point): bool
    {
        return ($point->status & 2) && is_numeric($point->latitude) && is_numeric($point->longitude)
            && abs((float) $point->latitude) <= 90 && abs((float) $point->longitude) <= 180
            && ((float) $point->latitude !== 0.0 || (float) $point->longitude !== 0.0)
            && (float) $point->speed >= 0 && (float) $point->speed <= 250;
    }

    private function point(object $p): array
    {
        return ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude];
    }

    private function distance(object $a, object $b): float
    {
        $lat1 = deg2rad((float) $a->latitude);
        $lat2 = deg2rad((float) $b->latitude);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad($b->longitude - $a->longitude) / 2) ** 2;

        return 6371.0088 * 2 * atan2(sqrt(max(0, $h)), sqrt(max(0, 1 - $h)));
    }

    private function state(object $point): string
    {
        return $point->speed >= 3 ? 'moving' : (($point->status & 1) ? 'stopped' : 'parking');
    }

    private function window(?Dashcam $camera, ?Carbon $fence, Carbon $from, Carbon $to, bool $details, int $minimumStop = self::STOP_SECONDS): array
    {
        $totals = $this->empty();
        $segments = [];
        $events = [];
        $daily = [];
        $activeDays = [];
        $blocks = [];
        $first = null;
        $last = null;
        $previous = null;
        $previousTime = null;
        $start = $fence ? $from->copy()->max($fence) : $from->copy();
        $totals['period_seconds'] = max(0, (int) $start->diffInSeconds($to, false));
        if (! $camera || ! $fence || $start->gte($to)) {
            $totals['unknown_seconds'] = $totals['period_seconds'];

            return ['totals' => $totals, 'segments' => [], 'events' => [], 'daily' => [], 'first_at' => null, 'last_at' => null];
        }
        $base = DB::table('dashcam_positions')->where('dashcam_id', $camera->id)->where('recorded_at', '>=', $fence);
        $priorAlarm = (int) ((clone $base)->where('recorded_at', '<', $start)->orderByDesc('recorded_at')->orderByDesc('id')->value('alarm') ?? 0);
        $points = (clone $base)->where('recorded_at', '>=', $start)->where('recorded_at', '<', $to)->orderBy('recorded_at')->orderBy('id')
            ->select(['id', 'recorded_at', 'latitude', 'longitude', 'speed', 'status', 'alarm'])->limit(500001)->cursor();
        foreach ($points as $p) {
            if (++$this->read > 500000 || ($this->read % 1000 === 0 && microtime(true) > $this->deadline)) {
                throw new \RuntimeException('too_large');
            }
            $time = Carbon::parse($p->recorded_at, 'UTC')->getTimestamp();
            $day = gmdate('Y-m-d', $time + 3600);
            $daily[$day] ??= ['distance_km' => 0.0, 'moving_seconds' => 0, 'alerts' => 0, 'known_seconds' => 0];
            $valid = $this->valid($p);
            $mask = (int) $p->alarm & ~$priorAlarm;
            $priorAlarm = (int) $p->alarm;
            for ($bit = 0; $bit < 32; $bit++) {
                if ($mask & (1 << $bit)) {
                    $totals['alerts']++;
                    $daily[$day]['alerts']++;
                    if ($details) {
                        $events[] = ['at' => gmdate('Y-m-d\TH:i:s\Z', $time), 'alarm_bit' => $bit, 'position' => $valid ? $this->point($p) : null, 'speed' => $valid ? (float) $p->speed : null];
                    }
                }
            }
            if ($valid) {
                $totals['valid_points']++;
                $totals['max_speed'] = max($totals['max_speed'], (float) $p->speed);
                $first ??= gmdate('Y-m-d\TH:i:s\Z', $time);
                $last = gmdate('Y-m-d\TH:i:s\Z', $time);

            } else {
                $totals['invalid_points']++;
            }
            if ($previous !== null && $time > $previousTime) {
                $dt = $time - $previousTime;
                $distance = $valid && $this->valid($previous) ? $this->distance($previous, $p) : null;
                $plausible = $distance !== null && $distance <= (max((float) $p->speed, (float) $previous->speed) + 30) * $dt / 3600 + 0.05;
                if ($dt <= self::GAP_SECONDS && $plausible) {
                    $state = $this->state($previous);
                    $distance = $state === 'moving' || $this->state($p) === 'moving' ? $distance : 0.0;
                    $totals[$state.'_seconds'] += $dt;
                    $totals['known_seconds'] += $dt;
                    $totals['distance_km'] += $distance;
                    $this->dailyInterval($daily, $previousTime, $time, $distance, $state === 'moving');
                    $index = count($blocks) - 1;
                    if ($index < 0 || $blocks[$index]['state'] !== $state || $blocks[$index]['end_ts'] !== $previousTime) {
                        $blocks[] = ['state' => $state, 'start_ts' => $previousTime, 'end_ts' => $time, 'distance_km' => 0.0, 'max_speed' => 0.0,
                            'start_position' => $this->point($previous), 'end_position' => $this->point($p), 'path' => $details ? [$this->point($previous)] : [], 'daily' => [], 'moving_seconds' => 0, 'stopped_seconds' => 0,
                            'bounds' => [(float) $previous->latitude, (float) $previous->latitude, (float) $previous->longitude, (float) $previous->longitude]];
                        $index++;
                    }
                    $blocks[$index]['end_ts'] = $time;
                    $this->dailyInterval($blocks[$index]['daily'], $previousTime, $time, $distance, $state === 'moving');
                    if ($state === 'moving' || $state === 'stopped') {
                        $blocks[$index][$state.'_seconds'] += $dt;
                    }
                    $b = $blocks[$index]['bounds'];
                    $blocks[$index]['bounds'] = [min($b[0], $p->latitude), max($b[1], $p->latitude), min($b[2], $p->longitude), max($b[3], $p->longitude)];
                    $blocks[$index]['distance_km'] += $distance;
                    $blocks[$index]['max_speed'] = max($blocks[$index]['max_speed'], (float) $p->speed, (float) $previous->speed);
                    $blocks[$index]['end_position'] = $this->point($p);
                    if ($details && $state === 'moving') {
                        $blocks[$index]['path'][] = $this->point($p);
                        if (count($blocks[$index]['path']) > 512) {
                            $blocks[$index]['path'] = array_values(array_filter($blocks[$index]['path'], fn ($key) => $key % 2 === 0, ARRAY_FILTER_USE_KEY));
                        }
                    }
                } else {
                    $totals['gaps']++;
                }
            }
            $previous = $p;
            $previousTime = $time;
            if (count($blocks) + count($events) > 20000) {
                throw new \RuntimeException('too_large');
            }
        }
        // Short stops with ACC on can belong to the same trip; parking and gaps never do.
        for ($i = 0; $i < count($blocks); $i++) {
            $block = $blocks[$i];
            if ($block['state'] === 'moving') {
                while (isset($blocks[$i + 2]) && $blocks[$i + 1]['state'] === 'stopped'
                    && $blocks[$i + 1]['end_ts'] - $blocks[$i + 1]['start_ts'] < self::STOP_SECONDS
                    && $blocks[$i + 2]['state'] === 'moving' && $block['end_ts'] === $blocks[$i + 1]['start_ts']
                    && $blocks[$i + 1]['end_ts'] === $blocks[$i + 2]['start_ts']) {
                    $stop = $blocks[$i + 1];
                    $next = $blocks[$i + 2];
                    $block['end_ts'] = $next['end_ts'];
                    $block['end_position'] = $next['end_position'];
                    $block['distance_km'] += $stop['distance_km'] + $next['distance_km'];
                    $block['max_speed'] = max($block['max_speed'], $next['max_speed']);
                    $block['path'] = [...$block['path'], ...$next['path']];
                    foreach ([$stop, $next] as $part) {
                        $block['moving_seconds'] += $part['moving_seconds'];
                        $block['stopped_seconds'] += $part['stopped_seconds'];
                        foreach ($part['daily'] as $date => $values) {
                            $block['daily'][$date] ??= array_fill_keys(array_keys($values), 0);
                            foreach ($values as $key => $value) {
                                $block['daily'][$date][$key] += $value;
                            }
                        }
                        $b = $block['bounds'];
                        $n = $part['bounds'];
                        $block['bounds'] = [min($b[0], $n[0]), max($b[1], $n[1]), min($b[2], $n[2]), max($b[3], $n[3])];
                    }
                    $i += 2;
                    while (count($block['path']) > 512) {
                        $block['path'] = array_values(array_filter($block['path'], fn ($key) => $key % 2 === 0, ARRAY_FILTER_USE_KEY));
                    }
                }
                $b = $block['bounds'];
                $span = $this->distance((object) ['latitude' => $b[0], 'longitude' => $b[2]], (object) ['latitude' => $b[1], 'longitude' => $b[3]]);
                // Isolated speed spikes and small GPS drift cannot establish a trip.
                if ($block['end_ts'] - $block['start_ts'] < 60 || $block['distance_km'] < 0.1 || $span < 0.05) {
                    $totals['distance_km'] -= $block['distance_km'];
                    $totals['moving_seconds'] -= $block['moving_seconds'];
                    $totals['stopped_seconds'] -= $block['stopped_seconds'];
                    $totals['known_seconds'] -= $block['end_ts'] - $block['start_ts'];
                    foreach ($block['daily'] as $date => $values) {
                        foreach ($values as $key => $value) {
                            $daily[$date][$key] = max(0, $daily[$date][$key] - $value);
                        }
                    }

                    continue;
                }
                foreach ($block['daily'] as $date => $values) {
                    if ($values['moving_seconds'] > 0) {
                        $activeDays[$date] = true;
                    }
                }
                $totals['trips']++;
            } elseif ($minimumStop <= $block['end_ts'] - $block['start_ts']) {
                $totals['stops']++;
            } else {
                continue;
            }
            if ($details) {
                $block['path'][] = $block['end_position'];
                $segments[] = [...array_diff_key($block, array_flip(['start_ts', 'end_ts', 'daily', 'bounds', 'moving_seconds', 'stopped_seconds'])),
                    'start' => gmdate('Y-m-d\TH:i:s\Z', $block['start_ts']), 'end' => gmdate('Y-m-d\TH:i:s\Z', $block['end_ts']),
                    'duration_seconds' => $block['end_ts'] - $block['start_ts'], 'distance_km' => round($block['distance_km'], 3)];
            }
        }
        $totals['active_days'] = count($activeDays);
        $totals['unknown_seconds'] = max(0, $totals['period_seconds'] - $totals['known_seconds']);
        $totals['distance_km'] = round($totals['distance_km'], 3);

        return ['totals' => $totals, 'segments' => $segments, 'events' => $events, 'daily' => $daily, 'first_at' => $first, 'last_at' => $last];
    }

    private function dailyInterval(array &$daily, int $from, int $to, float $distance, bool $moving): void
    {
        $total = $to - $from;
        for ($at = $from; $at < $to;) {
            $day = gmdate('Y-m-d', $at + 3600);
            $next = min($to, strtotime($day.'T00:00:00+01:00') + 86400);
            $daily[$day] ??= ['distance_km' => 0.0, 'moving_seconds' => 0, 'alerts' => 0, 'known_seconds' => 0];
            $daily[$day]['known_seconds'] += $next - $at;
            $daily[$day]['distance_km'] += $distance * ($next - $at) / $total;
            if ($moving) {
                $daily[$day]['moving_seconds'] += $next - $at;
            }
            $at = $next;
        }
    }
}
