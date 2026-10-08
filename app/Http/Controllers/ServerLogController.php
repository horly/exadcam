<?php

namespace App\Http\Controllers;

use App\Services\ServerLogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerLogController extends Controller
{
    public function content(Request $request, ServerLogReader $reader): JsonResponse
    {
        abort_unless($request->user()?->isActive() && $request->user()->isSuperadmin(), 403);
        $data = $request->validate([
            'source' => ['sometimes', Rule::in(ServerLogReader::SOURCES)],
            'lines' => ['sometimes', 'integer', Rule::in([100, 300, 600, 1000])],
            'level' => ['sometimes', Rule::in(['all', 'errors'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($reader->read($data['source'] ?? 'gps', (int) ($data['lines'] ?? 300),
            $data['level'] ?? 'all', $data['search'] ?? ''))->header('Cache-Control', 'no-store, private');
    }
}
