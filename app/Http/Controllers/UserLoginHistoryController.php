<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserLoginHistoryController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        Gate::authorize('view', $user);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['id', 'device', 'ip_address', 'logged_in_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($data['search'] ?? '');
        $histories = $user->loginHistories()->reorder()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('device', 'like', "%{$search}%")->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('logged_in_at', 'like', "%{$search}%")))
            ->orderBy($data['sort'] ?? 'logged_in_at', $data['direction'] ?? 'desc')->orderByDesc('id')
            ->paginate(5);

        return response()->json([
            'html' => view('users.history', ['histories' => $histories, 'sort' => $data['sort'] ?? 'logged_in_at',
                'direction' => $data['direction'] ?? 'desc'])->render(),
            'meta' => ['page' => $histories->currentPage(), 'last_page' => $histories->lastPage(), 'total' => $histories->total()],
        ]);
    }
}
