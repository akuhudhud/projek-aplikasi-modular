<?php

namespace App\Console;

use App\Services\MarketplaceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            app(MarketplaceService::class)->expireDueItems();
        })
            ->everyMinute()
            ->withoutOverlapping();
    }
        protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
