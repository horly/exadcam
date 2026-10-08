<?php

namespace App\Http\Controllers;

use App\Services\ServerMetricsReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerMonitoringController extends Controller
{
    public function metrics(Request $request, ServerMetricsReader $reader): JsonResponse
    {
        abort_unless($request->user()?->isActive() && $request->user()->isSuperadmin(), 403);

        return response()->json($reader->read())->header('Cache-Control', 'no-store, private');
    }
}
