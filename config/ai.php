<?php

return [

    'base_url' => rtrim(env('AI_SERVICE_BASE_URL', 'http://127.0.0.1:8001'), '/'),

    'timeout_seconds' => (int) env('AI_SERVICE_TIMEOUT_SECONDS', 60),

    'internal_key' => env('AI_SERVICE_INTERNAL_KEY'),

];
