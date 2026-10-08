<?php

return [
    // Suggested destination only, never assumed to be a camera's current value.
    'server_host' => env('SMARTVISION_SERVER_HOST', '62.171.190.15'),
    'server_port' => (int) env('SMARTVISION_SERVER_PORT', 7808),
];
