<?php

return [
    'admin_name' => env('INITIAL_ADMIN_NAME', 'Administración ByeCode'),
    'admin_email' => env('INITIAL_ADMIN_EMAIL'),
    'admin_password' => env('INITIAL_ADMIN_PASSWORD'),
    'commerce_team_password' => env('COMMERCE_TEAM_INITIAL_PASSWORD'),
    'mail_enabled' => env('TRANSACTIONAL_MAIL_ENABLED', false),
];
