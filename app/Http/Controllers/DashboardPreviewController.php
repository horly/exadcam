<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BrandingService;
use App\Services\DashboardService;
use App\Support\FleetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPreviewController extends Controller
{
    public function __invoke(DashboardService $service): View
    {
        $googleMap = [
            'apiKey' => (string) config('services.google_maps.api_key'),
            'mapId' => (string) config('services.google_maps.map_id'),
            'mapType' => app(BrandingService::class)->settings()['map_type'],
            'locale' => app()->getLocale(),
            'iconsUrl' => asset('images/icons.svg'),
            'center' => ['lat' => -4.325, 'lng' => 15.299],
            'positionsUrl' => route('map.vehicles'),
            'videoUrl' => url('/dashcams'),
            'canVideo' => FleetAccess::allows(auth()->user(), User::PERMISSION_VIDEO_VIEW),
            'allowed' => FleetAccess::allows(auth()->user(), User::PERMISSION_MAP_VIEW),
            'labels' => trans('map'),
        ];

        $dashboard = $service->snapshot(auth()->user());

        return view('welcome', ['dashboard' => $dashboard, 'dashboardCharts' => $dashboard['charts'],
            'dashboardVideo' => collect($dashboard['video']), 'googleMap' => $googleMap]);
    }

    public function data(Request $request, DashboardService $service): JsonResponse
    {
        return response()->json($service->snapshot($request->user()));
    }

    public function recentAlerts(Request $request, DashboardService $service): JsonResponse
    {
        $cursor = $request->validate([
            'after_alarm' => ['required_with:after_connection_at,after_connection_id', 'integer', 'min:0'],
            'after_connection_at' => ['required_with:after_alarm,after_connection_id', 'date_format:Y-m-d H:i:s'],
            'after_connection_id' => ['required_with:after_alarm,after_connection_at', 'integer', 'min:0'],
        ]);
        return response()->json($service->recentAlerts($request->user(), $cursor))
            ->header('Cache-Control', 'private, no-store');
    }

    public function alerts(Request $request, DashboardService $service): JsonResponse
    {
        $validated = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000000']]);

        return response()->json($service->alerts($request->user(), (int) ($validated['page'] ?? 1)));
    }
}
