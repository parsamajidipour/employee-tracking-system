<?php

return [

    'timezone' => env('TRACKING_TIMEZONE', 'Asia/Muscat'),

    // Location history retention in days. Change this value without changing application code.
    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 7),

    'online_threshold_seconds' => (int) env('TRACKING_ONLINE_THRESHOLD_SECONDS', 30),

];
