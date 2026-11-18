<?php

namespace App\Contracts;

use App\Models\CoreTask;

interface CoreTaskLifecycle
{
    public function start(
        string $taskId,
        string $actorAccountId
    ): CoreTask;

    public function arrive(
        string $taskId,
        string $actorAccountId,
        float $latitude,
        float $longitude
    ): CoreTask;

    public function complete(
        string $taskId,
        string $actorAccountId,
        ?string $completionProofPath = null
    ): CoreTask;
}
