<?php

namespace App\Services;

use App\Contracts\CoreTaskRegistry;
use App\Models\CoreTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class CoreTaskRegistryService implements CoreTaskRegistry
{
    public function register(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId,
        ?string $createdByAccountId = null
    ): CoreTask {
        $moduleKey = trim($moduleKey);
        $taskType = trim($taskType);
        $sourceType = trim($sourceType);
        $sourceId = trim($sourceId);

        if (
            $moduleKey === ''
            || $taskType === ''
            || $sourceType === ''
            || $sourceId === ''
        ) {
            throw new InvalidArgumentException(
                'Task registration identity fields must not be empty.'
            );
        }

        if (! $this->supportsTaskType($moduleKey, $taskType)) {
            throw new InvalidArgumentException(
                'The module or task type is not registered or enabled.'
            );
        }

        return DB::transaction(function () use (
            $moduleKey,
            $taskType,
            $sourceType,
            $sourceId,
            $createdByAccountId
        ): CoreTask {
            return CoreTask::firstOrCreate(
                [
                    'module_key' => $moduleKey,
                    'task_type' => $taskType,
                    'source_id' => $sourceId,
                ],
                [
                    'task_id' => (string) Str::uuid(),
                    'source_type' => $sourceType,
                    'created_by_account_id' => $createdByAccountId,
                    'generic_status' => CoreTask::STATUS_OPEN,
                ]
            );
        });
    }

    private function supportsTaskType(
        string $moduleKey,
        string $taskType
    ): bool {
        $moduleConfigFiles = glob(
            base_path('../modules/*/Config/module.php')
        );

        if ($moduleConfigFiles === false) {
            return false;
        }

        foreach ($moduleConfigFiles as $moduleConfigFile) {
            try {
                $metadata = require $moduleConfigFile;
            } catch (Throwable) {
                continue;
            }

            if (! is_array($metadata)) {
                continue;
            }

            if (($metadata['name'] ?? null) !== $moduleKey) {
                continue;
            }

            if (($metadata['enabled'] ?? false) !== true) {
                return false;
            }

            $taskTypes = $metadata['task_types'] ?? [];

            return is_array($taskTypes)
                && in_array($taskType, $taskTypes, true);
        }

        return false;
    }
}
