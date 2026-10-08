<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFleetReport;
use App\Models\FleetReportPreset;
use App\Models\FleetReportRun;
use App\Models\User;
use App\Services\BrandingService;
use App\Services\FleetReportScope;
use App\Support\FleetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FleetReportController extends Controller
{
    public function options(Request $request, FleetReportScope $scope): JsonResponse
    {
        $choices = $scope->options($request->user());

        return response()->json([...$choices, 'presets' => FleetReportPreset::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'filters']),
            'latest_run' => FleetReportRun::where('user_id', $request->user()->id)->where('expires_at', '>', now())->latest()->value('id')]);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Africa/Kinshasa')->format('Y-m-d')],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now('Africa/Kinshasa')->format('Y-m-d')],
            'fleet_id' => ['nullable', 'integer', 'min:1'], 'department_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_ids' => ['sometimes', 'array', 'max:100'], 'vehicle_ids.*' => ['integer', 'min:1', 'distinct'],
            'type' => ['sometimes', 'in:summary,trips,usage,safety'],
        ]);
        abort_if(Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) > 30, 422, __('reports.limit_days'));

        return ['from' => $data['from'], 'to' => $data['to'], 'type' => $data['type'] ?? 'summary',
            'fleet_id' => isset($data['fleet_id']) ? (int) $data['fleet_id'] : null, 'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            'vehicle_ids' => array_values(array_map('intval', $data['vehicle_ids'] ?? []))];
    }

    public function store(Request $request, FleetReportScope $scope): JsonResponse
    {
        $scope->authorize($request->user());
        $filters = $this->filters($request);
        $vehicles = $scope->vehicles($request->user(), $filters);
        abort_if($vehicles->isEmpty(), 422, __('reports.no_vehicles'));
        $run = DB::transaction(function () use ($request, $scope, $filters, $vehicles) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if(FleetReportRun::where('user_id', $request->user()->id)->whereIn('status', ['queued', 'running'])->where('created_at', '>', now()->subMinutes(10))->count() >= 2, 429, __('reports.busy'));

            return FleetReportRun::create(['id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
                'filters' => $filters, 'vehicle_ids' => $vehicles->pluck('id')->all(), 'scope_hash' => $scope->hash($request->user(), $vehicles), 'expires_at' => now()->addDay()]);
        });
        try {
            GenerateFleetReport::dispatch($run->id)->onConnection('database')->onQueue('reports');
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'error_code' => 'failed']);
            throw $error;
        }

        return response()->json(['id' => $run->id, 'status' => 'queued'], 202);
    }

    private function result(Request $request, FleetReportRun $run, FleetReportScope $scope): array
    {
        $scope->checkRun($request->user(), $run);
        abort_unless($run->status === 'ready', 409, __('reports.not_ready'));
        $disk = Storage::disk('local');
        abort_unless($disk->exists($run->resultPath()), 410, __('reports.expired'));
        abort_if($disk->size($run->resultPath()) > 24 * 1024 * 1024, 503);

        $data = json_decode($disk->get($run->resultPath()), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(($data['method']['version'] ?? 0) === 2, 410, __('reports.expired'));

        return $data;
    }

    private function columns(string $type): array
    {
        $columns = match ($type) {
            'trips' => ['vehicle' => 'text', 'state_label' => 'text', 'start' => 'datetime', 'end' => 'datetime', 'duration_seconds' => 'duration', 'distance_km' => 'km', 'max_speed' => 'speed'],
            'usage' => ['vehicle' => 'text', 'fleet' => 'text', 'active_days' => 'integer', 'moving_seconds' => 'duration', 'stopped_seconds' => 'duration', 'parking_seconds' => 'duration', 'unknown_seconds' => 'duration', 'coverage' => 'percent'],
            'safety' => ['at' => 'datetime', 'vehicle' => 'text', 'fleet' => 'text', 'alarm_label' => 'text', 'speed' => 'speed', 'position_label' => 'text'],
            default => ['vehicle' => 'text', 'fleet' => 'text', 'distance_km' => 'km', 'moving_seconds' => 'duration', 'trips' => 'integer', 'alerts' => 'integer', 'coverage' => 'percent', 'quality_label' => 'text'],
        };

        return collect($columns)->map(fn ($format, $key) => ['key' => $key, 'label' => __('reports.col_'.$key), 'format' => $format])->values()->all();
    }

    private function table(Request $request, array $data, bool $all = false): array
    {
        $input = $request->validate(['type' => ['sometimes', 'in:summary,trips,usage,safety'], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'in:5,10,25,50'], 'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'max:32'], 'direction' => ['sometimes', 'in:asc,desc']]);
        $type = $input['type'] ?? 'summary';
        $columns = $this->columns($type);
        $canMap = FleetAccess::allows($request->user(), User::PERMISSION_MAP_VIEW);
        $canVideo = FleetAccess::allows($request->user(), User::PERMISSION_VIDEO_VIEW);
        $source = $data[match ($type) {
            'trips' => 'segments', 'safety' => 'events', default => 'vehicles'
        }];
        $rows = [];
        foreach ($source as $row) {
            $row['vehicle'] = trim($row['vehicle'].($row['registration'] ? ' · '.$row['registration'] : ''));
            $row['state_label'] = isset($row['state']) ? __('reports.state_'.$row['state']) : null;
            $row['quality_label'] = isset($row['quality']) ? __('reports.quality_'.$row['quality']) : null;
            $row['alarm_label'] = isset($row['alarm_bit']) ? $this->alarm($row['alarm_bit']) : null;
            $row['position_label'] = $canMap && ($row['position'] ?? null) ? number_format($row['position']['lat'], 5, '.', '').', '.number_format($row['position']['lng'], 5, '.', '') : '—';
            $row['has_map'] = $canMap && ($type === 'trips' || ($type === 'safety' && ($row['position'] ?? null)));
            $row['has_video'] = $canVideo && ($row['camera_id'] ?? null) && in_array($type, ['trips', 'safety'], true);
            if (isset($row['known_seconds']) && $row['known_seconds'] === 0) {
                $row['distance_km'] = null;
                $row['trips'] = null;
            }
            $row['map_label'] = __('reports.'.(($row['state'] ?? null) === 'moving' ? 'map' : 'position'));
            $keys = [...array_column($columns, 'key'), 'id', 'vehicle_id', 'has_map', 'has_video', 'map_label'];
            $row = array_intersect_key($row, array_flip($keys));
            $search = mb_strtolower(trim($input['search'] ?? ''));
            if ($search !== '' && ! str_contains(mb_strtolower(implode(' ', array_map(fn ($key) => (string) ($row[$key] ?? ''), array_column($columns, 'key')))), $search)) {
                continue;
            }
            $rows[] = $row;
        }
        $sort = $input['sort'] ?? match ($type) {
            'trips' => 'start', 'safety' => 'at', default => 'vehicle'
        };
        abort_unless(in_array($sort, array_column($columns, 'key'), true), 422, __('reports.invalid_sort'));
        $direction = $input['direction'] ?? (in_array($sort, ['at', 'start'], true) ? 'desc' : 'asc');
        usort($rows, function ($a, $b) use ($sort, $direction) {
            $aValue = $a[$sort] ?? null;
            $bValue = $b[$sort] ?? null;
            $order = is_numeric($aValue) && is_numeric($bValue) ? $aValue <=> $bValue : strnatcasecmp((string) $aValue, (string) $bValue);

            return ($order ?: (($a['id'] ?? $a['vehicle_id']) <=> ($b['id'] ?? $b['vehicle_id']))) * ($direction === 'desc' ? -1 : 1);
        });
        $count = count($rows);
        $size = (int) ($input['per_page'] ?? 5);
        $pages = max(1, (int) ceil($count / $size));
        $page = min($pages, (int) ($input['page'] ?? 1));

        return ['type' => $type, 'columns' => $columns, 'rows' => $all ? $rows : array_slice($rows, ($page - 1) * $size, $size),
            'total' => $count, 'unfiltered_total' => count($source), 'page' => $page, 'last_page' => $pages, 'per_page' => $size,
            'from' => $count ? ($page - 1) * $size + 1 : 0, 'to' => min($page * $size, $count), 'sort' => $sort, 'direction' => $direction];
    }

    public function show(Request $request, FleetReportRun $run, FleetReportScope $scope): JsonResponse
    {
        $scope->checkRun($request->user(), $run);
        if (in_array($run->status, ['queued', 'running'], true) && $run->updated_at->lt(now()->subMinutes(10))) {
            $run->update(['status' => 'failed', 'error_code' => 'failed']);
        }
        $meta = ['id' => $run->id, 'status' => $run->status, 'filters' => $run->filters, 'expires_at' => $run->expires_at->toIso8601String(),
            'error' => $run->error_code ? __('reports.'.$run->error_code) : null];
        if ($run->status !== 'ready') {
            return response()->json($meta);
        }
        $data = $this->result($request, $run, $scope);

        return response()->json([...$meta, 'period' => $data['period'], 'metrics' => $data['metrics'], 'previous' => $data['previous'], 'daily' => $data['daily'], ...$this->table($request, $data)]);
    }

    public function export(Request $request, FleetReportRun $run, FleetReportScope $scope, BrandingService $branding): JsonResponse
    {
        $data = $this->result($request, $run, $scope);
        $brand = $branding->context($request->user());
        $fleets = array_values(array_unique(array_column($data['vehicles'], 'fleet')));

        return response()->json(['period' => $data['period'], 'metrics' => $data['metrics'], 'daily' => $data['daily'],
            'branding' => ['name' => $brand['global'] ? $brand['settings']['short_name'] : 'EXADCAM', 'fleet' => implode(' · ', $fleets),
                'logo' => $brand['fleet_logo'] ?? $brand['logo'], 'color' => $brand['colors']['primary_color']],
            ...$this->table($request, $data, true)]);
    }

    public function detail(Request $request, FleetReportRun $run, string $kind, int $index, FleetReportScope $scope): JsonResponse
    {
        $data = $this->result($request, $run, $scope);
        abort_unless(in_array($kind, ['trips', 'safety'], true), 404);
        $row = $data[$kind === 'trips' ? 'segments' : 'events'][$index] ?? null;
        abort_unless($row, 404);
        $canMap = FleetAccess::allows($request->user(), User::PERMISSION_MAP_VIEW);
        $canVideo = FleetAccess::allows($request->user(), User::PERMISSION_VIDEO_VIEW);

        $path = isset($row['state']) && $row['state'] !== 'moving' ? [$row['start_position']] : ($row['path'] ?? (($row['position'] ?? null) ? [$row['position']] : []));

        return response()->json(['vehicle' => $row['vehicle'], 'registration' => $row['registration'], 'vehicle_id' => $row['vehicle_id'],
            'start' => $row['start'] ?? $row['at'], 'end' => $row['end'] ?? $row['at'],
            'path' => $canMap ? $path : [],
            'camera_id' => $canVideo ? $row['camera_id'] : null]);
    }

    public function savePreset(Request $request, FleetReportScope $scope): JsonResponse
    {
        $scope->authorize($request->user());
        $request->validate(['name' => ['required', 'string', 'max:80']]);
        $filters = $this->filters($request);
        $scope->vehicles($request->user(), $filters);
        $filters['days'] = (int) Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) + 1;
        unset($filters['from'], $filters['to']);
        $preset = DB::transaction(function () use ($request, $filters) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = FleetReportPreset::where('user_id', $request->user()->id);
            abort_if((clone $query)->count() >= 20 && ! (clone $query)->where('name', $request->string('name')->trim()->toString())->exists(), 422, __('reports.preset_limit'));

            return FleetReportPreset::updateOrCreate(['user_id' => $request->user()->id, 'name' => trim($request->input('name'))], ['filters' => $filters]);
        });

        return response()->json(['id' => $preset->id, 'name' => $preset->name, 'filters' => $preset->filters]);
    }

    public function deletePreset(Request $request, FleetReportPreset $preset, FleetReportScope $scope): JsonResponse
    {
        $scope->authorize($request->user());
        abort_unless($preset->user_id === $request->user()->id, 404);
        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    private function alarm(int $bit): string
    {
        return __('reports.alarm_'.(in_array($bit, [0, 1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 29, 30], true) ? $bit : 'other'));
    }
}
