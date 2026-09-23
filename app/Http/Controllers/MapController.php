<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FleetMapService;
use App\Services\MapDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function details(Request $request, int $vehicle, MapDetailsService $details): JsonResponse
    {
        abort_unless($request->user()->hasClientPermission(User::PERMISSION_MAP_VIEW), 403);
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:tomorrow'],
            'timezone' => ['required', 'timezone:all'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ]);

        return response()->json($details->get($request->user(), $vehicle, $data))->header('Cache-Control', 'private, no-store');
    }

    public function vehicles(Request $request, FleetMapService $map): JsonResponse
    {
        abort_unless($request->user()->hasClientPermission(User::PERMISSION_MAP_VIEW), 403);

        return response()->json($map->snapshot($request->user()))->header('Cache-Control', 'private, no-store');
    }
}
