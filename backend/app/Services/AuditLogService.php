<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;

class AuditLogService
{
    public function record(
        Account $actor,
        Account $target,
        string $action
    ): AuditLog {
        return AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => $action,
        ]);
    }
}
