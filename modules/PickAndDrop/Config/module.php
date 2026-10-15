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
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Supported Core Task Types
    |--------------------------------------------------------------------------
    |
    | The module declares task types it can register with Core.
    | Core reads this metadata without depending on module classes or tables.
    |
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
    |
    | Pick & Drop owns business behaviour and must consume Core through
    | approved contracts/services. Core must never depend on this module.
    |
    */

    'namespace' => 'Modules\\PickAndDrop',

    'base_path' => base_path('../modules/PickAndDrop'),

    'routes' => [
        'api' => base_path('../modules/PickAndDrop/Routes/api.php'),
    ],
];
