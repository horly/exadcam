<?php

namespace App\Services;

use App\Models\Dashcam;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\FleetAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // Only current assignments may contribute to a fleet's history.
    private function positions(User $user): Builder
    {
        return DB::table('dashcam_positions as p')
            ->join('dashcams as d', 'd.id', '=', 'p.dashcam_id')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->join('fleets as f', 'f.id', '=', 'v.fleet_id')
            ->whereIn('d.id', FleetAccess::scopeDashcams(Dashcam::query(), $user)->select('dashcams.id'))
            ->where('d.enabled', true)
            ->whereColumn('p.recorded_at', '>=', 'd.vehicle_assigned_at')
            ->whereColumn('p.recorded_at', '>=', 'v.fleet_assigned_at')
            ->where('p.recorded_at', '<=', now()->addSeconds(30));
    }

    public function snapshot(User $user): array
    {
        $canBrowse = FleetAccess::browse($user);
        $canMap = FleetAccess::allows($user, User::PERMISSION_MAP_VIEW);
        $canVideo = FleetAccess::allows($user, User::PERMISSION_VIDEO_VIEW);
        $cameras = $canBrowse ? FleetAccess::scopeDashcams(Dashcam::query(), $user)
            ->where('enabled', true)->with('vehicle.fleet')->orderByDesc('last_seen_at')->orderBy('id')->get() : collect();
        $vehicles = $canBrowse ? FleetAccess::scopeRegistry(Vehicle::query(), $user, 'vehicles')
            ->with('fleet:id,name')->orderBy('name')->get() : collect();
        $byVehicle = $cameras->whereNotNull('vehicle_id')->groupBy('vehicle_id');
        $cutoff = now()->subMinutes(3);
        $isOnline = fn ($camera) => $camera?->last_seen_at && $camera->last_seen_at->gte($cutoff);
        $map = $canMap ? collect(app(FleetMapService::class)->snapshot($user)['vehicles'])->keyBy('id') : collect();
        $rows = $vehicles->map(function ($vehicle) use ($byVehicle, $isOnline, $map, $canMap, $canVideo) {
            $camera = $byVehicle->get($vehicle->id)?->first();
            $online = (bool) $isOnline($camera);
            $state = ! $camera ? 'no_camera' : (! $camera->last_seen_at ? 'pending' : ($online ? 'online' : 'offline'));
            $gps = $map->get($vehicle->id);
            return [
                'id' => $vehicle->id, 'name' => $vehicle->name, 'registration' => $vehicle->registration_number,
                'fleet' => $vehicle->fleet->name, 'online' => $online, 'connection' => $state,
                'status' => $this->connectionLabel($state),
                'motion' => $canMap && $camera ? __('map.'.($gps['state'] ?? 'no_position')) : null,
                'speed' => $canMap && $online && in_array($gps['state'] ?? '', ['moving', 'stopped', 'parking'], true) ? $gps['position']['speed'] : null,
                'last_seen_at' => $camera?->last_seen_at?->toIso8601String(),
                'device_id' => $canVideo ? $camera?->id : null, 'model' => $camera?->model,
            ];
        })->values();
        $video = $canVideo ? $byVehicle->map(fn ($group) => $group->first())->map(function ($camera) use ($rows) {
            $row = $rows->firstWhere('id', $camera->vehicle_id);
            return ['id' => $camera->vehicle_id,
                'label' => collect([$camera->vehicle->name, $camera->vehicle->registration_number, $camera->vehicle->fleet->name])->filter()->implode(' · '),
                'device_id' => $camera->id, 'model' => $camera->model, 'channels' => $camera->channels,
                'status' => $row['status'].($row['online'] && $row['motion'] ? ' · '.$row['motion'] : ''),
                'connection' => $row['connection'], 'last_seen_at' => $row['last_seen_at'],
            ];
        })->sortBy('label')->values() : collect();
        $online = $cameras->filter($isOnline)->count();
        $alerts = $canMap ? $this->alerts($user, 1, 5) : ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1];
        return [
            'generated_at' => now()->toIso8601String(), 'can_map' => $canMap, 'can_video' => $canVideo,
            'metrics' => ['vehicles' => $byVehicle->count(), 'fleets' => $vehicles->pluck('fleet_id')->unique()->count(),
                'online' => $online, 'dashcams' => $cameras->count(), 'offline' => $cameras->count() - $online,
                'channels' => $cameras->sum('channels'), 'alerts' => $alerts['total']],
            'vehicles' => $rows, 'video' => $video, 'alerts' => $alerts,
            'charts' => $canMap ? $this->charts($user, $vehicles->count(), $online, $cameras->count()) : null,
        ];
    }

    private function connectionLabel(string $state): string
    {
        return match ($state) {
            'online' => __('En ligne'), 'offline' => __('Hors ligne'),
            'pending' => __('En attente de connexion'), default => __('Sans dashcam active'),
        };
    }

    private function charts(User $user, int $vehicles, int $online, int $total): array
    {
        $zone = 'Africa/Kinshasa';
        $end = now()->setTimezone($zone);
        $weekStart = $end->copy()->startOfDay()->subDays(6);
        $start = $weekStart->copy()->utc();
        // Aggregate in SQL; no coordinates or individual history leave this service.
        $bucket = DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d %H:00:00', p.recorded_at)" : "DATE_FORMAT(p.recorded_at, '%Y-%m-%d %H:00:00')";
        $hours = $this->positions($user)->where('p.recorded_at', '>=', $start)
            ->whereRaw('(p.status & 2) = 2')->whereBetween('p.latitude', [-90,90])->whereBetween('p.longitude', [-180,180])
            ->where(fn ($q) => $q->where('p.latitude', '!=', 0)->orWhere('p.longitude', '!=', 0))
            ->selectRaw("$bucket as bucket, v.id as vehicle_id, MAX(CASE WHEN (p.status & 1) = 1 AND p.speed >= 3 THEN 1 ELSE 0 END) as moving")
            ->groupByRaw("$bucket, v.id")->get();
        $periods = [];
        foreach (['day' => 24, 'week' => 7] as $key => $length) {
            $first = $key === 'day' ? $end->copy()->startOfHour()->subHours(23) : $weekStart;
            $period = ['caption' => __('Relevés GPS reçus · heure de Kinshasa'), 'categories' => [], 'online' => [], 'moving' => []];
            for ($i = 0; $i < $length; $i++) {
                $a = $key === 'day' ? $first->copy()->addHours($i) : $first->copy()->addDays($i);
                $b = $key === 'day' ? $a->copy()->addHour() : $a->copy()->addDay();
                $found = $hours->filter(fn ($row) => Carbon::parse($row->bucket, 'UTC')->gte($a) && Carbon::parse($row->bucket, 'UTC')->lt($b));
                $period['categories'][] = $a->format($key === 'day' ? 'H:i' : 'd/m');
                $period['online'][] = $found->pluck('vehicle_id')->unique()->count();
                $period['moving'][] = $found->where('moving', 1)->pluck('vehicle_id')->unique()->count();
            }
            $periods[$key] = $period;
        }
        return ['locale' => app()->getLocale(), 'total' => $vehicles, 'camera_total' => $total,
            'labels' => ['online' => __('Avec relevé GPS'), 'moving' => __('En déplacement'), 'vehicles' => __('véhicules'), 'dashcams' => __('Dashcams')],
            'periods' => $periods, 'status' => ['labels' => [__('En ligne'), __('Sans contact récent')], 'series' => [$online, $total - $online]]];
    }

    private function alertQuery(User $user): Builder
    {
        // The predecessor is NOT restricted to the display period. A continuing alarm
        // must not become a new event when the seven-day window advances.
        $since = now()->subDays(7);
        $previousIds = FleetAccess::scopeDashcams(Dashcam::query(), $user)->where('enabled', true)
            ->whereNotNull('vehicle_id')->select('id')->selectSub(DB::table('dashcam_positions')
                ->select('id')->whereColumn('dashcam_id', 'dashcams.id')->where('recorded_at', '<', $since)
                ->orderByDesc('recorded_at')->orderByDesc('id')->limit(1), 'previous_id')->get()->pluck('previous_id')->filter();
        $fields = ['p.id', 'p.dashcam_id', 'd.id as camera_id', 'v.id as vehicle_id', 'v.name', 'v.registration_number', 'f.name as fleet', 'd.model', 'p.recorded_at as occurred_at', 'p.alarm'];
        $period = $this->positions($user)->where('p.recorded_at', '>=', $since)->select($fields);
        $predecessors = $this->positions($user)->whereIn('p.id', $previousIds)->select($fields);
        // A single ordered pass includes zero/reset packets, not a correlated
        // predecessor lookup for every alarm packet. Both branches retain the fences.
        $signals = DB::query()->fromSub($period->unionAll($predecessors), 'raw_signals')->select('raw_signals.*')
            ->selectRaw('LAG(alarm) OVER (PARTITION BY dashcam_id ORDER BY occurred_at, id) as previous_alarm');
        $alarms = DB::query()->fromSub($signals, 'signals')->select(['id','camera_id','vehicle_id','name','registration_number','fleet','model','occurred_at'])
            ->selectRaw("'alarm' as kind, (alarm & ~COALESCE(previous_alarm, 0)) as mask")
            ->where('occurred_at', '>=', $since)->whereRaw('(alarm & ~COALESCE(previous_alarm, 0)) <> 0');
        $offline = DB::table('dashcams as d')->join('vehicles as v','v.id','=','d.vehicle_id')->join('fleets as f','f.id','=','v.fleet_id')
            ->whereIn('d.id', FleetAccess::scopeDashcams(Dashcam::query(), $user)->select('dashcams.id'))
            ->where('d.enabled', true)->where('d.last_seen_at', '<', now()->subMinutes(3))
            ->whereColumn('d.last_seen_at','>=','d.vehicle_assigned_at')->whereColumn('d.last_seen_at','>=','v.fleet_assigned_at')
            ->select(['d.id','d.id as camera_id','v.id as vehicle_id','v.name','v.registration_number','f.name as fleet','d.model','d.last_seen_at as occurred_at'])
            ->selectRaw("'connection' as kind, 0 as mask");
        return DB::query()->fromSub($alarms->unionAll($offline), 'events');
    }

    public function alerts(User $user, int $page = 1, int $perPage = 10): array
    {
        abort_unless(FleetAccess::allows($user, User::PERMISSION_MAP_VIEW), 403);
        $query = $this->alertQuery($user);
        $total = (clone $query)->count();
        $last = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $last);
        $rows = $query->orderByDesc('occurred_at')->orderBy('kind')->orderByDesc('id')->forPage($page, $perPage)->get();
        $data = $rows->map(function ($row) {
            $connection = $row->kind === 'connection';
            return ['id' => $row->kind.'-'.$row->id, 'vehicle_id' => (int) $row->vehicle_id,
                'vehicle' => $row->name, 'registration' => $row->registration_number, 'fleet' => $row->fleet,
                'model' => $row->model, 'at' => Carbon::parse($row->occurred_at, 'UTC')->toIso8601String(),
                'kind' => $row->kind, 'title' => $connection ? __('Perte de contact') : $this->alarmLabel((int) $row->mask),
                'description' => $connection ? __('Aucun contact reçu depuis plus de 3 minutes. La date indiquée est celle du dernier contact.') : __('Signalement transmis par la dashcam à la date indiquée.'),
            ];
        })->all();
        return ['data' => $data, 'total' => $total, 'page' => $page, 'last_page' => $last];
    }

    private function alarmLabel(int $mask): string
    {
        // JT808 base alarm flags. Proprietary ADAS/DMS extensions are not inferred.
        $known = [0 => 'SOS', 1 => 'Dépassement de vitesse', 2 => 'Fatigue au volant',
            4 => 'Défaut GPS', 5 => 'Antenne GPS déconnectée', 6 => 'Défaut antenne GPS',
            7 => 'Batterie faible', 8 => 'Alimentation coupée', 9 => 'Défaut écran', 10 => 'Défaut synthèse vocale',
            11 => 'Défaut caméra', 29 => 'Alerte collision', 30 => 'Alerte retournement'];
        $labels = [];
        foreach ($known as $bit => $label) {
            if ($mask & (1 << $bit)) { $labels[] = __($label); $mask &= ~(1 << $bit); }
        }
        if ($mask) { $labels[] = __('Autre alarme équipement'); }
        return implode(' · ', $labels);
    }
}
