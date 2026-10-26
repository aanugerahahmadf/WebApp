<?php

return [
    /*
     * Emergency mode is deliberately opt-in. Set SECURITY_EMERGENCY_LOCKDOWN=true
     * at the edge when an incident requires all API writes to stop immediately.
     */
    'emergency_lockdown' => (bool) env('SECURITY_EMERGENCY_LOCKDOWN', false),
    'emergency_bypass_token' => env('SECURITY_EMERGENCY_BYPASS_TOKEN'),
    'backup_enabled' => (bool) env('SECURITY_BACKUP_ENABLED', false),

    'headers' => [
        'hsts' => (bool) env('SECURITY_HSTS', false),
        'frame_ancestors' => env('SECURITY_FRAME_ANCESTORS', "'self'"),
    ],

    'rate_limits' => [
        'api' => (int) env('SECURITY_API_RATE_LIMIT', 120),
        'auth' => (int) env('SECURITY_AUTH_RATE_LIMIT', 10),
        'admin' => (int) env('SECURITY_ADMIN_RATE_LIMIT', 60),
    ],
];
