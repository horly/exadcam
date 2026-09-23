<?php

namespace App\Http\Controllers;

use App\Models\Dashcam;
use App\Services\AudioAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ListenerController extends Controller
{
    public function audioAccess(Request $request): JsonResponse
    {
        $data = $request->validate(['grant' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'device_id' => ['required', 'integer']]);
        abort_unless(AudioAccess::valid($data['grant'], (int) $data['device_id']), 403);

        return response()->json(['allowed' => true]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['2013', '2019', 'video', 'id'])],
            'terminal' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
        ]);
        $field = match ($data['kind']) {
            '2013' => 'terminal_id_2013', '2019' => 'terminal_id_2019', 'video' => 'video_terminal_id', 'id' => 'id',
        };
        $dashcam = Dashcam::where($field, $data['terminal'])->where('enabled', true)->firstOrFail();

        return response()->json(['id' => $dashcam->id, 'imei' => $dashcam->imei,
            'video_terminal_id' => $dashcam->video_terminal_id, 'channels' => $dashcam->channels,
            'normalize_video_timestamps' => (bool) $dashcam->normalize_video_timestamps, 'frame_rate' => $dashcam->frame_rate, 'gps_timezone_minutes' => $dashcam->gps_timezone_minutes, 'auth_token' => $dashcam->auth_token])->header('Cache-Control', 'no-store');
    }

    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer'], 'protocol' => ['required', Rule::in(['2013', '2019'])],
            'ip' => ['required', 'ip'], 'position' => ['nullable', 'array'],
            'position.latitude' => ['required_with:position', 'numeric', 'between:-90,90'],
            'position.longitude' => ['required_with:position', 'numeric', 'between:-180,180'],
            'position.speed' => ['required_with:position', 'numeric', 'between:0,1000'],
            'position.recorded_at' => ['required_with:position', 'date', 'after:2020-01-01', 'before:tomorrow'],
            'position.alarm' => ['required_with:position', 'integer', 'between:0,4294967295'],
            'position.status' => ['required_with:position', 'integer', 'between:0,4294967295'],
        ]);
        DB::transaction(function () use ($data): void {
            $dashcam = Dashcam::where('enabled', true)->lockForUpdate()->findOrFail($data['device_id']);
            $dashcam->last_seen_at = now();
            $dashcam->last_protocol = $data['protocol'];
            $dashcam->last_ip = $data['ip'];
            if (! empty($data['position'])) {
                $position = $data['position'];
                $recordedAt = Carbon::parse($position['recorded_at'])->utc();
                DB::table('dashcam_positions')->insert([
                    ...$position, 'recorded_at' => $recordedAt, 'dashcam_id' => $dashcam->id, 'created_at' => now(),
                ]);
                if (! $dashcam->position_at || $recordedAt->gte($dashcam->position_at)) {
                    $dashcam->latitude = $position['latitude'];
                    $dashcam->longitude = $position['longitude'];
                    $dashcam->speed = $position['speed'];
                    $dashcam->position_at = $recordedAt;
                }
            }
            $dashcam->save();
        });

        return response()->json(['accepted' => true]);
    }
}
