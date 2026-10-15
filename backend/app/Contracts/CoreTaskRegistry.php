<?php

namespace App\Contracts;

use App\Models\CoreTask;

interface CoreTaskRegistry
{
    /**
     * Register a module-owned task in the generic Core registry.
     *
     * Repeated registration with the same identity must return
     * the existing task instead of creating a duplicate.
     */
    public function register(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId,
        ?string $createdByAccountId = null
    ): CoreTask;
}
