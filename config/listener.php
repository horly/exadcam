<?php

return [
    // Terminal clock observed on the commissioned cameras in Kinshasa (UTC+1).
    'default_gps_timezone_minutes' => (int) env('DASHCAM_GPS_TIMEZONE_MINUTES', 60),
    'token' => env('LISTENER_API_TOKEN', ''),
    'gps_url' => env('LISTENER_GPS_URL', 'http://127.0.0.1:3001'),
    'video_url' => env('LISTENER_VIDEO_URL', 'http://127.0.0.1:3002'),
    'recording_url' => env('LISTENER_RECORDING_URL', 'http://127.0.0.1:3004'),
    'recording_storage' => env('RECORDING_STORAGE', '/var/lib/exadcam-recordings'),
    'audio_url' => env('LISTENER_AUDIO_URL', 'http://127.0.0.1:3003'),
];
