<?php

return [
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
        'secret_key' => env('SUPABASE_SECRET_KEY'),
        'jwks_url' => env('SUPABASE_JWKS_URL'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    ],
];
