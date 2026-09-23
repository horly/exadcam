<?php

namespace App\Http\Controllers;

use App\Models\Dashcam;
use App\Services\AudioAccess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AudioController extends Controller
{
    public function start(Request $request, Dashcam $dashcam): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['listen', 'talk'])]]);
        abort_unless(AudioAccess::permitted($request->user(), $dashcam, $data['mode']), 403);
        $grant = bin2hex(random_bytes(32));
        Cache::put('audio-grant:'.$grant, AudioAccess::snapshot($request->user(), $dashcam, $data['mode']), now()->addSeconds(60));
        try {
            $result = $this->node('/sessions', ['device_id' => $dashcam->id, 'mode' => $data['mode'], 'grant' => $grant]);
            abort_unless(preg_match('/^[a-f0-9-]{36}$/', $result['lease_id'] ?? ''), 503);
        } catch (\Throwable $error) {
            Cache::forget('audio-grant:'.$grant);
            throw $error;
        }
        Cache::put($this->key($request, $dashcam, $result['lease_id']), $grant, now()->addMinutes(2));
        Log::info('Audio session opened', ['user_id' => $request->user()->id, 'dashcam_id' => $dashcam->id, 'mode' => $data['mode']]);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function keepalive(Request $request, Dashcam $dashcam, string $lease): JsonResponse
    {
        $key = $this->key($request, $dashcam, $lease);
        $grant = Cache::get($key);
        abort_unless(is_string($grant), 403);
        if (! AudioAccess::valid($grant, $dashcam->id)) {
            Cache::forget('audio-grant:'.$grant);
            Cache::forget($key);
            try {
                $this->node('/sessions/'.$lease.'/stop', []);
            } catch (\Throwable) {
                // The audio service independently rechecks the grant every 3 seconds.
            }
            abort(403);
        }
        $record = Cache::get('audio-grant:'.$grant);
        Cache::put('audio-grant:'.$grant, $record, now()->addSeconds(20));
        $result = $this->node('/sessions/'.$lease.'/keepalive', []);
        Cache::put($key, $grant, now()->addMinutes(2));

        return response()->json($result);
    }

    public function stop(Request $request, Dashcam $dashcam, string $lease): JsonResponse
    {
        $key = $this->key($request, $dashcam, $lease);
        $grant = Cache::pull($key);
        abort_unless(is_string($grant), 403);
        Cache::forget('audio-grant:'.$grant);
        // Grant revocation takes effect even if the audio service is unreachable.
        try {
            $this->node('/sessions/'.$lease.'/stop', []);
        } catch (\Throwable) {
        }

        return response()->json(['stopped' => true]);
    }

    private function key(Request $request, Dashcam $camera, string $lease): string
    {
        return 'audio:'.hash('sha256', $request->session()->getId()).':'.$request->user()->id.':'.$camera->id.':'.$lease;
    }

    private function node(string $path, array $data): array
    {
        $token = (string) config('listener.token');
        abort_if(strlen($token) < 32, 503, __('audio.unavailable'));
        try {
            $response = Http::withToken($token)->acceptJson()->connectTimeout(2)->timeout(12)
                ->post(config('listener.audio_url').$path, $data);
        } catch (ConnectionException) {
            abort(503, __('audio.unavailable'));
        }
        if (! $response->successful()) {
            $code = $response->json('code');
            abort(in_array($response->status(), [403, 404, 409, 422]) ? $response->status() : 503,
                __('audio.'.(in_array($code, ['busy', 'offline', 'unsupported']) ? $code : 'unavailable')));
        }

        return $response->json();
    }
}
