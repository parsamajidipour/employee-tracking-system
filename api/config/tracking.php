<?php

return [

    'timezone' => env('TRACKING_TIMEZONE', 'Asia/Muscat'),

    // Location history retention in days. Change this value without changing application code.
    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 7),

    'online_threshold_seconds' => (int) env('TRACKING_ONLINE_THRESHOLD_SECONDS', 30),

    // A consecutive pair of points closer together than this is GPS noise around a
    // stationary employee, not movement, and contributes 0 to distance_m rather than
    // the raw (jittery) geodesic distance between them.
    'stationary_noise_floor_m' => (float) env('TRACKING_STATIONARY_NOISE_FLOOR_M', 8.0),

    // A site-survey photo captured further than this from the case's property
    // location is flagged (never blocked) as not GPS-verified for admin review.
    'case_photo_radius_m' => (float) env('TRACKING_CASE_PHOTO_RADIUS_M', 150.0),

];
