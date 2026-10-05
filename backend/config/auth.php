<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | The Core currently uses its own session authentication middleware.
    | Laravel's default Auth guard/provider is not used.
    |
    */

    'defaults' => [
        'guard' => null,
        'passwords' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | No Laravel authentication guards are currently configured.
    | Core authentication is handled by AuthenticateSession.
    |
    */

    'guards' => [],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | No Laravel user providers are currently configured.
    | Core accounts are resolved through the custom session system.
    |
    */

    'providers' => [],

    /*
    |--------------------------------------------------------------------------
    | Password Resetting
    |--------------------------------------------------------------------------
    |
    | Password reset is not implemented in the current Core foundation.
    |
    */

    'passwords' => [],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Laravel password confirmation is not currently used by Core.
    |
    */

    'password_timeout' => 10800,

];
