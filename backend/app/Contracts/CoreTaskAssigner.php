<?php

namespace App\Contracts;

use App\Models\CoreTask;

interface CoreTaskAssigner
{
    public function assign(
        string $taskId,
        string $assigneeAccountId
    ): CoreTask;
}
