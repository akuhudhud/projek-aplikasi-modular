<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Identity
    |--------------------------------------------------------------------------
    |
    | Basic identity information for the Pick & Drop business module.
    |
    */

    'name' => 'PickAndDrop',

    'display_name' => 'Pick & Drop',

    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Module Status
    |--------------------------------------------------------------------------
    |
    | The module is initially registered as an internal foundation.
    | Business functionality will be enabled in later implementation phases.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Module Provider
    |--------------------------------------------------------------------------
    |
    | The module declares its own service provider.
    | Core discovers providers through a generic mechanism.
    |
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
