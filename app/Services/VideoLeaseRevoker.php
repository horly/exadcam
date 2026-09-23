<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VideoLeaseRevoker
{
    public function revoke(int $cameraId): void
    {
        try {
            $token = (string) config('listener.token');
            if (strlen($token) < 32) {
                return;
            }
            Http::withToken($token)->acceptJson()->connectTimeout(2)->timeout(3)
                ->post(config('listener.video_url').'/revoke', ['device_id' => $cameraId])->throw();
        } catch (\Throwable) {
            // Web renewals recheck fleet access; unrenewed media leases expire.
            Log::warning('Video lease revocation pending expiry', ['dashcam_id' => $cameraId]);
        }
    }
}
