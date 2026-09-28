<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Fleet;
use App\Models\Vehicle;
use App\Services\VideoLeaseRevoker;
use App\Support\FleetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FleetRegistryController extends Controller
{
    private function model(string $kind): string
    {
        return match ($kind) {
            'fleets' => Fleet::class,
            'departments' => Department::class,
            'vehicles' => Vehicle::class,
        };
    }

    public function index(Request $request, string $kind): JsonResponse
    {
        abort_unless(FleetAccess::registry($request->user(), $kind), 403);
        $query = match ($kind) {
            'fleets' => Fleet::query()->withCount('vehicles'),
            'departments' => Department::query()->with('fleet')->withCount('vehicles'),
            'vehicles' => Vehicle::query()->with(['fleet', 'department'])->withCount('dashcams'),
        };
        FleetAccess::scopeRegistry($query, $request->user(), $kind);
        $search = mb_substr(trim((string) $request->query('search')), 0, 100);
        if ($search !== '') {
            $query->where(function ($query) use ($search, $kind) {
                $query->where('name', 'like', '%'.$search.'%')->orWhere($kind === 'vehicles' ? 'registration_number' : 'code', 'like', '%'.$search.'%');
                if ($kind !== 'fleets') {
                    $query->orWhereHas('fleet', fn ($fleet) => $fleet->where('name', 'like', '%'.$search.'%'));
                }
                if ($kind === 'vehicles') {
                    $query->orWhereHas('department', fn ($department) => $department->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
                }
            });
        }
        $sort = in_array($request->query('sort'), ['name', 'id', $kind === 'vehicles' ? 'registration_number' : 'code'], true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->query('per_page'), [5, 10, 25, 50], true) ? (int) $request->query('per_page') : 10;
        $records = $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);

        $canManage = FleetAccess::registry($request->user(), $kind, true);
        $html = view('registry.table', compact('records', 'kind', 'sort', 'direction', 'canManage'))->render();
        if (! $request->user()->isSuperadmin()) {
            $records->getCollection()->each(function ($record) use ($kind): void {
                $record->setVisible(match ($kind) {
                    'fleets' => ['id', 'name', 'code', 'description', 'status', 'vehicles_count'],
                    'departments' => ['id', 'name', 'code', 'description', 'fleet_id', 'fleet', 'vehicles_count'],
                    'vehicles' => ['id', 'name', 'registration_number', 'brand', 'model', 'fleet_id', 'department_id', 'fleet', 'department', 'dashcams_count'],
                });
                if ($kind !== 'fleets') {
                    $record->fleet?->setVisible(['id', 'name', 'code']);
                }
                if ($kind === 'vehicles') {
                    $record->department?->setVisible(['id', 'name', 'code', 'fleet_id']);
                }
            });
        }

        return response()->json(['records' => $records, 'html' => $html]);
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        abort_unless(FleetAccess::createRegistry($request->user(), $kind), 403);
        $record = DB::transaction(function () use ($request, $kind) {
            $data = $this->validated($request, $kind);
            if ($kind === 'vehicles') {
                $this->checkDepartment($data['department_id'] ?? null, (int) $data['fleet_id']);
                $data['created_by'] = $request->user()->id;
            }

            return ($this->model($kind))::create($data);
        }, 3);

        return response()->json(['id' => $record->id, 'message' => __('dashcams.saved')], 201);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        abort_unless(FleetAccess::registry($request->user(), $kind, true), 403);
        DB::transaction(function () use ($request, $kind, $id) {
            $record = FleetAccess::scopeRegistry(($this->model($kind))::query(), $request->user(), $kind)->lockForUpdate()->findOrFail($id);
            $data = $this->validated($request, $kind, $id);
            if ($kind === 'vehicles') {
                // Omitting the optional field preserves it; explicit null removes it.
                $departmentId = array_key_exists('department_id', $data) ? $data['department_id'] : $record->department_id;
                $this->checkDepartment($departmentId, (int) $data['fleet_id']);
            }
            if ($kind === 'departments' && (int) $data['fleet_id'] !== $record->fleet_id && $record->vehicles()->exists()) {
                throw ValidationException::withMessages(['fleet_id' => __('dashcams.department_fleet_locked')]);
            }
            $transferredCameras = $kind === 'vehicles' && (int) $data['fleet_id'] !== $record->fleet_id ? $record->dashcams()->pluck('id')->all() : [];
            $record->update($data);
            if ($transferredCameras) {
                DB::afterCommit(function () use ($transferredCameras): void {
                    foreach ($transferredCameras as $cameraId) {
                        app(VideoLeaseRevoker::class)->revoke($cameraId);
                    }
                });
            }
        }, 3);

        return response()->json(['message' => __('dashcams.saved')]);
    }

    private function checkDepartment(mixed $id, int $fleetId): void
    {
        if ($id === null) {
            return;
        }
        $department = Department::lockForUpdate()->find($id);
        if (! $department || $department->fleet_id !== $fleetId) {
            throw ValidationException::withMessages(['department_id' => __('dashcams.department_same_fleet')]);
        }
    }

    private function validated(Request $request, string $kind, ?int $id = null): array
    {
        if (! $request->user()->isSuperadmin()) {
            $request->mergeIfMissing(['fleet_id' => $request->user()->fleet_id]);
        }
        $rules = match ($kind) {
            'fleets' => [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:32', Rule::unique('fleets', 'code')->ignore($id)],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
            'departments' => [
                'fleet_id' => ['required', 'integer', 'exists:fleets,id'],
                'name' => ['required', 'string', 'max:255', Rule::unique('departments')->where('fleet_id', $request->input('fleet_id'))->ignore($id)],
                'code' => ['nullable', 'string', 'max:50', Rule::unique('departments')->where('fleet_id', $request->input('fleet_id'))->ignore($id)],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
            'vehicles' => [
                'name' => ['required', 'string', 'max:255'],
                'fleet_id' => ['required', 'integer', 'exists:fleets,id'],
                'department_id' => ['nullable', 'integer', 'exists:departments,id'],
                'registration_number' => ['nullable', 'string', 'max:40', Rule::unique('vehicles')->where('fleet_id', $request->input('fleet_id'))->ignore($id)],
                'brand' => ['nullable', 'string', 'max:80'], 'model' => ['nullable', 'string', 'max:80'],
            ],
        };

        if ($kind !== 'fleets' && ! $request->user()->isSuperadmin()) {
            $rules['fleet_id'][] = Rule::in([$request->user()->fleet_id]);
        }

        return $request->validate($rules, [], __('dashcams.attributes'));
    }
}
