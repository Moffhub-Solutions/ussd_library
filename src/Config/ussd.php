<?php

declare(strict_types=1);

return [
    'ussd' => [
        'enabled' => env('USSD_ENABLED', false),
        'default_menu' => env('USSD_DEFAULT_MENU', 'main'),
    ],
];
