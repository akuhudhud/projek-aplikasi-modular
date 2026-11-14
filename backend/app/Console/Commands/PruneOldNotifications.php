<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOldNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete notifications older than 90 days.';

    public function handle(): int
    {
        $cutoff = now()->subDays(90);

        $deleted = DB::table('notifications')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(
            sprintf(
                'Deleted %d notification(s) older than 90 days.',
                $deleted
            )
        );

        return self::SUCCESS;
    }
}
