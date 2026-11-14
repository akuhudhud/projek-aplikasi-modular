<?php

namespace App\Console;

use App\Services\MarketplaceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Marketplace: luputkan item yang sudah tamat.
        $schedule->call(function (): void {
            app(MarketplaceService::class)->expireDueItems();
        })
            ->everyMinute()
            ->withoutOverlapping();

        // Notifications: padam notifikasi melebihi 90 hari.
        $schedule->command('notifications:prune')
            ->dailyAt('02:00')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
