<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', User::class);
        if (! $request->expectsJson()) {
            return redirect(route('dashboard').'#users');
        }

        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['id', 'name', 'email', 'role', 'phone', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([5, 10, 25, 50])],
        ]);
        $actor = $request->user();
        $query = User::query()->when(! $actor->isSuperadmin(), fn ($query) => $query
            ->where('fleet_id', $actor->fleet_id ?? 0)->where('role', UserRole::User->value));
        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->active()->count(),
            'admins' => (clone $query)->whereIn('role', ['superadmin', 'admin'])->count(),
        ];
        $search = trim($input['search'] ?? '');
        $sort = $input['sort'] ?? 'id';
        $direction = $input['direction'] ?? 'desc';
        $users = $query->with('fleet:id,name,code')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
                ->orWhere('role', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::Superadmin->value])
            ->orderBy($sort, $direction)->orderByDesc('id')
            ->paginate((int) ($input['per_page'] ?? 5));

        return response()->json([
            'html' => view('users.table', compact('users', 'sort', 'direction'))->render(),
            'records' => $users->getCollection()->map(fn (User $user): array => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => $user->role->value, 'fleet_id' => $user->fleet_id,
                'phone' => $user->phone, 'address' => $user->address,
                'permissions' => $user->permissions ?? [],
            ]),
            'stats' => $stats,
            'meta' => ['page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'total' => $users->total()],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        return response()->json(['fleets' => Fleet::query()->visibleTo($request->user())
            ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data): void {
            $fleet = $this->activeFleet((int) $data['fleet_id']);
            $user = User::query()->create([
                ...$this->attributes($data, $fleet), 'created_by' => $request->user()->id,
                'status' => 'active', 'password' => $data['password'],
            ]);
            $this->attachFleet($user, $fleet);
        });

        return response()->json(['message' => __('users.created')], 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::authorize('update', $user);
            $data = $request->validated();
            $fleet = $this->activeFleet((int) $data['fleet_id']);
            $user->fill($this->attributes($data, $fleet));
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            if ($user->isDirty(['email', 'password', 'role', 'permissions', 'fleet_id'])) {
                $user->remember_token = Str::random(60);
                $this->revokeSessions($user);
            }
            $user->save();
            $this->attachFleet($user, $fleet);
        });

        return response()->json(['message' => __('users.updated')]);
    }

    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::authorize('delete', $user);
            $this->revokeSessions($user);
            $user->fleets()->detach();
            $user->delete();
        });

        return response()->json(['message' => __('users.deleted')]);
    }

    private function activeFleet(int $id): Fleet
    {
        $fleet = Fleet::query()->lockForUpdate()->find($id);
        if (! $fleet || $fleet->status !== 'active') {
            throw ValidationException::withMessages(['fleet_id' => __('users.fleet_required')]);
        }

        return $fleet;
    }

    private function attributes(array $data, Fleet $fleet): array
    {
        return [
            'name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'],
            'fleet_id' => $fleet->id, 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null,
            'permissions' => $data['role'] === 'user' ? ($data['permissions'] ?? []) : [],
        ];
    }

    private function attachFleet(User $user, Fleet $fleet): void
    {
        $user->fleets()->sync([$fleet->id => ['permission' => $user->isAdmin() ? 'manager' : 'viewer']]);
    }

    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)->delete();
        }
    }
}
