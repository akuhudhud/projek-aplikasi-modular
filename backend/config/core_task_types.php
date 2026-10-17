<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Core-Owned Task Types
    |--------------------------------------------------------------------------
    |
    | These task types are owned by platform-level engines and must not
    | depend on an optional business module being enabled.
    |
    */

    'Marketplace' => [
        'enabled' => true,

        'task_types' => [
            'MARKETPLACE_SERVICE_REQUEST',
        ],
    ],
];
