<?php

return [

    'promote_dry_run_script' => env('PROMOTE_TO_PROD_DRY_RUN_SCRIPT'),
    'promote_live_script' => env('PROMOTE_TO_PROD_LIVE_SCRIPT'),

    'theme_update_dry_run_script' => env('THEME_UPDATE_DRY_RUN_SCRIPT'),
    'theme_update_live_script' => env('THEME_UPDATE_LIVE_SCRIPT'),
    'theme_update_lock_ttl' => (int) env('THEME_UPDATE_LOCK_TTL', 900),
    'theme_update_server_host' => env('THEME_UPDATE_SERVER_HOST', 'local'),

    'theme_repo' => env('THEME_REPO', 'git@github.com:sergeys-commits/wp-theme-core.git'),
    'theme_src_path' => env('THEME_SRC_PATH', storage_path('app/theme-src')),
    'theme_artifacts_path' => env('THEME_ARTIFACTS_PATH', storage_path('app/theme-artifacts')),

];
