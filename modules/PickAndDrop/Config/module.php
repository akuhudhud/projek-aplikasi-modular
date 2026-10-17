<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Identity
    |--------------------------------------------------------------------------
    */

    'name' => 'PickAndDrop',

    'display_name' => 'Pick & Drop',

    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Module Status
    |--------------------------------------------------------------------------
    |
    | Pick & Drop is disabled for GIGAVERSE v0.4.0.
    | The Direct Runner Marketplace must remain service-agnostic.
    | Keep the module files and migration history intact until the
    | dependency and database impact audit is complete.
    |
    */

    'enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Supported Core Task Types
    |--------------------------------------------------------------------------
    */

    'task_types' => [
        'PICK_AND_DROP_REQUEST',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Provider
    |--------------------------------------------------------------------------
    */

    'provider' => \Modules\PickAndDrop\Providers\PickAndDropServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | Module Boundaries
    |--------------------------------------------------------------------------
    */

    'namespace' => 'Modules\\PickAndDrop',

    'base_path' => base_path('../modules/PickAndDrop'),

    'routes' => [
        'api' => base_path('../modules/PickAndDrop/Routes/api.php'),
    ],
];
