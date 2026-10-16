<?php

namespace App\Contracts;

use App\Models\CoreTask;

interface CoreTaskLifecycle
{
    public function start(
        string $taskId,
        string $actorAccountId
    ): CoreTask;

    public function complete(
        string $taskId,
        string $actorAccountId
    ): CoreTask;
}
