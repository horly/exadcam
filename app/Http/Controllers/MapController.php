<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FleetMapService;
use App\Services\MapDetailsService;
use App\Services\MapTripHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MapController extends Controller
{
    public function details(Request $request, int $vehicle, MapDetailsService $details): JsonResponse
    {
        abort_unless($request->user()->hasClientPermission(User::PERMISSION_MAP_VIEW), 403);
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'min:1'],
            'summary_only' => ['sometimes', 'boolean'],
            'date' => ['required_unless:summary_only,1', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:tomorrow'],
            'timezone' => ['required_unless:summary_only,1', 'timezone:all'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ]);

        return response()->json($details->get($request->user(), $vehicle, $data))->header('Cache-Control', 'private, no-store');
    }

    public function trips(Request $request, int $vehicle, MapTripHistoryService $history): JsonResponse
    {
        abort_unless($request->user()->hasClientPermission(User::PERMISSION_MAP_VIEW), 403);
        $input = $request->validate([
            'source_id' => ['required', 'integer', 'min:1'],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:tomorrow'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:tomorrow'],
            'timezone' => ['required', 'timezone:all'],
        ]);
        abort_if(Carbon::parse($input['from'])->diffInDays($input['to']) > 31, 422, __('map.trips_period_limit'));

        return response()->json($history->get($request->user(), $vehicle, $input))->header('Cache-Control', 'private, no-store');
    }

    public function vehicles(Request $request, FleetMapService $map): JsonResponse
    {
        abort_unless($request->user()->hasClientPermission(User::PERMISSION_MAP_VIEW), 403);

        return response()->json($map->snapshot($request->user()))->header('Cache-Control', 'private, no-store');
    }
}
