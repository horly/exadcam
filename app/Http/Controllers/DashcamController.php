<?php

namespace App\Http\Controllers;

use App\Models\Dashcam;
use App\Models\Department;
use App\Models\Fleet;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VideoLeaseRevoker;
use App\Support\DashcamProfile;
use App\Support\FleetAccess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class DashcamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(FleetAccess::dashcams($request->user()), 403);
        $platform = $request->user()->isSuperadmin();
        $query = FleetAccess::scopeDashcams(Dashcam::with('vehicle.fleet'), $request->user());
        $base = clone $query;
        $search = mb_substr(trim((string) $request->query('search')), 0, 100);
        if ($search !== '') {
            $query->where(function ($query) use ($search, $platform) {
                foreach ($platform ? ['name', 'imei', 'model', 'terminal_id_2013'] : [] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
                $query->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('name', 'like', '%'.$search.'%')->orWhere('registration_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('vehicle.fleet', fn ($fleet) => $fleet->where('name', 'like', '%'.$search.'%'));
            });
        }
        $requestedModel = $request->query('model');
        $model = is_string($requestedModel) ? DashcamProfile::canonicalModel($requestedModel) : null;
        if ($platform && in_array($model, DashcamProfile::MODELS, true)) {
            $query->whereIn('model', [$model, DashcamProfile::listenerModel($model)]);
        }
        $sort = in_array($request->query('sort'), ['id', 'name', 'imei', 'model', 'enabled', 'last_seen_at'], true) ? $request->query('sort') : 'id';
        if (! $platform && in_array($sort, ['imei', 'model', 'name'], true)) {
            $sort = 'id';
        }
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->query('per_page'), [5, 10, 25, 50], true) ? (int) $request->query('per_page') : 10;
        $dashcams = $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);
        $stats = ['total' => (clone $base)->count(), 'online' => (clone $base)->where('enabled', true)->where('last_seen_at', '>=', now()->subMinutes(3))->count(), 'unassigned' => (clone $base)->whereNull('vehicle_id')->count()];

        $html = view('dashcams.table', compact('dashcams', 'sort', 'direction'))->render();
        if (! $platform) {
            $dashcams->getCollection()->each(function ($camera): void {
                $camera->setAttribute('name', $camera->vehicle?->name ?? __('dashcams.title'));
                $camera->setAttribute('video_fit', $camera->videoFit());
                $camera->setVisible(['id', 'name', 'video_fit', 'enabled', 'channels', 'last_seen_at', 'vehicle_id', 'vehicle']);
                $camera->vehicle?->setVisible(['id', 'name', 'registration_number', 'fleet_id', 'fleet']);
                $camera->vehicle?->fleet?->setVisible(['id', 'name']);
            });
        }

        return response()->json(['dashcams' => $dashcams, 'stats' => $stats, 'html' => $html]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless(FleetAccess::browse($request->user()), 403);

        return response()->json([
            'departments' => FleetAccess::scopeRegistry(Department::query(), $request->user(), 'departments')->orderBy('name')->get(['id', 'name', 'code', 'fleet_id']),
            'fleets' => FleetAccess::fleets($request->user())->orderBy('name')->get(['id', 'name', 'code']),
            'vehicles' => FleetAccess::scopeRegistry(Vehicle::with('fleet:id,name,code'), $request->user(), 'vehicles')->orderBy('name')->get(['id', 'name', 'fleet_id', 'registration_number']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);
        $data = DashcamProfile::validate($request->all());
        $dashcam = Dashcam::create([...$data, 'enabled' => true]);

        return response()->json(['id' => $dashcam->id, 'message' => __('dashcams.created')], 201);
    }

    public function update(Request $request, Dashcam $dashcam): JsonResponse
    {
        abort_unless(FleetAccess::allows($request->user(), User::PERMISSION_DASHCAMS_MANAGE), 403);
        if (! $request->user()->isSuperadmin()) {
            return $this->updateFleetCamera($request, $dashcam);
        }
        if ($request->has('model')) {
            $data = DashcamProfile::validate($request->all(), $dashcam);
        } else {
            // Maintenance changes preserve the already commissioned identities.
            $data = $request->validate([
                'enabled' => ['sometimes', 'boolean'], 'name' => ['sometimes', 'required', 'string', 'max:100'],
                'frame_rate' => ['sometimes', 'integer', 'between:1,30'], 'channels' => ['sometimes', 'integer', 'between:1,8'],
                'terminal_id_2013' => ['sometimes', 'string', 'regex:/^[0-9]{12}$/', Rule::unique('dashcams')->ignore($dashcam)],
                'terminal_id_2019' => ['sometimes', 'string', 'regex:/^[0-9]{20}$/', Rule::unique('dashcams')->ignore($dashcam)],
                'gps_timezone_minutes' => ['sometimes', 'nullable', 'integer', 'between:-720,840'],
                'normalize_video_timestamps' => ['sometimes', 'boolean'],
                'video_terminal_id' => ['sometimes', 'string', 'regex:/^(?:[0-9]{12}|[0-9]{20})$/', Rule::unique('dashcams')->ignore($dashcam)],
                'protocol_version' => ['prohibited'], 'transport' => ['prohibited'], 'imei' => ['prohibited'], 'vehicle_id' => ['prohibited'], 'communication_id' => ['prohibited'],
            ]);
            if ($dashcam->isSmartVision()) {
                $merged = [...$dashcam->getAttributes(), ...$data];
                validator($merged, [
                    'video_terminal_id' => [Rule::in([$merged['terminal_id_2013']])],
                    'normalize_video_timestamps' => ['accepted'],
                ])->validate();
            }
        }
        $oldFleetId = $dashcam->vehicle?->fleet_id;
        $dashcam->fill($data);
        $crossFleet = $dashcam->isDirty('vehicle_id') && $oldFleetId !== Vehicle::find($dashcam->vehicle_id)?->fleet_id;
        $reconnect = $dashcam->isDirty(['enabled', 'imei', 'model', 'protocol_version', 'terminal_id_2013', 'terminal_id_2019', 'video_terminal_id', 'channels', 'frame_rate', 'gps_timezone_minutes', 'normalize_video_timestamps']);
        $dashcam->save();
        if ($crossFleet && ! $reconnect) {
            app(VideoLeaseRevoker::class)->revoke($dashcam->id);
        }
        // Metadata edits within a fleet preserve ongoing streams.
        if ($reconnect) {
            foreach (['gps' => '/disconnect', 'video' => '/revoke'] as $service => $path) {
                try {
                    $this->node($service, $path, ['device_id' => $dashcam->id]);
                } catch (\Throwable) {
                    // Both listeners independently revalidate persisted authorization.
                }
            }
        }

        return response()->json(['message' => __('dashcams.updated')]);
    }

    private function updateFleetCamera(Request $request, Dashcam $dashcam): JsonResponse
    {
        $changed = DB::transaction(function () use ($request, $dashcam): array {
            $camera = FleetAccess::scopeDashcams(Dashcam::query(), $request->user())->lockForUpdate()->findOrFail($dashcam->id);
            abort_if(array_diff(array_keys($request->except('_token')), ['name', 'vehicle_id', 'enabled']), 403);
            $data = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:100'],
                'vehicle_id' => ['sometimes', 'required', 'integer', Rule::exists('vehicles', 'id')->where('fleet_id', $request->user()->fleet_id)],
                'enabled' => ['sometimes', 'boolean'],
            ], [], trans('dashcams.attributes'));
            if (isset($data['vehicle_id'])) {
                Vehicle::where('fleet_id', $request->user()->fleet_id)->lockForUpdate()->findOrFail($data['vehicle_id']);
            }
            $camera->fill($data);
            $changed = ['enabled' => $camera->isDirty('enabled'), 'assignment' => $camera->isDirty('vehicle_id')];
            $camera->save();

            return $changed;
        }, 3);
        if ($changed['enabled'] || $changed['assignment']) {
            foreach (['video' => '/revoke', ...($changed['enabled'] ? ['gps' => '/disconnect'] : [])] as $service => $path) {
                try {
                    $this->node($service, $path, ['device_id' => $dashcam->id]);
                } catch (\Throwable) {
                    // Listener authorization is also refreshed independently.
                }
            }
        }

        return response()->json(['message' => __('dashcams.updated')]);
    }

    public function start(Request $request, Dashcam $dashcam): JsonResponse
    {
        abort_unless(FleetAccess::allows($request->user(), User::PERMISSION_VIDEO_VIEW), 403);
        abort_unless(FleetAccess::camera($request->user(), $dashcam, User::PERMISSION_VIDEO_VIEW), 404);
        abort_unless($dashcam->enabled, 403);
        $data = $request->validate(['channel' => ['required', 'integer', 'between:1,'.$dashcam->channels]]);
        $result = $this->node('video', '/sessions', ['device_id' => $dashcam->id, 'channel' => (int) $data['channel']]);
        Cache::put($this->leaseKey($request, $dashcam, $result['lease_id']), true, now()->addMinutes(2));

        return response()->json($result);
    }

    public function keepalive(Request $request, Dashcam $dashcam, string $lease): JsonResponse
    {
        abort_unless($dashcam->enabled && Cache::has($this->leaseKey($request, $dashcam, $lease)), 403);
        if (! FleetAccess::camera($request->user(), $dashcam, User::PERMISSION_VIDEO_VIEW)) {
            try {
                $this->node('video', '/sessions/'.$lease.'/stop', []);
            } catch (\Throwable) {
                // Expired or unreachable leases cannot be renewed.
            }
            Cache::forget($this->leaseKey($request, $dashcam, $lease));
            abort(403);
        }
        $result = $this->node('video', '/sessions/'.$lease.'/keepalive', []);
        Cache::put($this->leaseKey($request, $dashcam, $lease), true, now()->addMinutes(2));

        return response()->json($result);
    }

    public function stop(Request $request, Dashcam $dashcam, string $lease): JsonResponse
    {
        $key = $this->leaseKey($request, $dashcam, $lease);
        abort_unless(Cache::has($key), 403);
        $this->node('video', '/sessions/'.$lease.'/stop', []);
        Cache::forget($key);

        return response()->json(['stopped' => true]);
    }

    private function leaseKey(Request $request, Dashcam $dashcam, string $lease): string
    {
        return 'video:'.hash('sha256', $request->session()->getId()).':'.$dashcam->id.':'.$lease;
    }

    private function node(string $service, string $path, array $data): array
    {
        $token = (string) config('listener.token');
        abort_if(strlen($token) < 32, 503, __('dashcams.unavailable'));
        try {
            $response = Http::withToken($token)->acceptJson()->connectTimeout(2)->timeout(12)
                ->post(config('listener.'.$service.'_url').$path, $data);
        } catch (ConnectionException) {
            abort(503, __('dashcams.unavailable'));
        }
        abort_if($response->status() === 422 && $response->json('code') === 'device_rejected', 422, __('dashcams.device_rejected'));
        abort_unless($response->successful(), $response->status() === 409 ? 409 : 503,
            $response->status() === 409 ? __('dashcams.offline') : __('dashcams.unavailable'));

        return $response->json();
    }
}
