<?php

namespace App\Providers;

use App\Contracts\OtpProviderInterface;
use App\Services\Otp\LocalOtpProvider;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OtpProviderInterface::class,
            LocalOtpProvider::class
        );
    }

    public function boot(): void
    {
        //
    }
}
