<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('APP_URL', 'http://localhost'),
        // Tauri desktop (v1 & v2)
        'tauri://localhost',
        'https://tauri.localhost',
        // Tauri mobile & Capacitor
        'capacitor://localhost',
        // Local dev servers (Vite, Tauri dev, etc.)
        'http://localhost:1420',
        'http://localhost:5173',
        'http://localhost:3000',
        'http://localhost:8080',
        // Android emulator (accessing host machine)
        'http://10.0.2.2',
        'http://10.0.2.2:5173',
        'http://10.0.2.2:1420',
        'http://10.0.2.2:8080',
    ],

    'allowed_origins_patterns' => [
        // Allow any device on local network (LAN testing for mobile)
        '#^https?://192\.168\.\d+\.\d+(:\d+)?$#',
        // Allow any localhost variant
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => true,

];
