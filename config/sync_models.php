<?php

return [
    // Master config
    'slaves' => env('SYNC_MODELS_SLAVES', []),
    'export_models' => [],
    'export_curl_opts' => [],
    'max_sync_attempts' => 5,
    'default_reset_chunk' => 3000,
    'default_post_chunk' => 1000,
    'rewrite_on_update' => true,

    // Slave config
    'import_models' => [],
    'auth_error_code' => 404,
    'allowed_ips' => env('SYNC_MODELS_IPS', []),
    'access_token' => env('SYNC_MODELS_TOKEN', base64_encode(random_bytes(32))) // deny default
];
