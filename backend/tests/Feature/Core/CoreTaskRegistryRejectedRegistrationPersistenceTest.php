<?php

namespace Tests\Feature\Core;

use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoreTaskRegistryRejectedRegistrationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public static function rejectedRegistrationProvider(): array
    {
        return [
            'unknown module' => [
                'UnknownModule',
                'UNKNOWN_TASK',
                'unknown_source',
                'rejected-unknown-module',
            ],
            'unsupported task type' => [
                'PickAndDrop',
                'UNDECLARED_TASK',
                'pick_and_drop_request',
                'rejected-unsupported-type',
            ],
            'incorrect module key case' => [
                'pickanddrop',
                'PICK_AND_DROP_REQUEST',
                'pick_and_drop_request',
                'rejected-module-case',
            ],
            'incorrect task type case' => [
                'PickAndDrop',
                'pick_and_drop_request',
                'pick_and_drop_request',
                'rejected-task-type-case',
            ],
        ];
    }

    #[DataProvider('rejectedRegistrationProvider')]
    public function test_rejected_registration_does_not_persist_a_task(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId
    ): void {
        $registry = new CoreTaskRegistryService();

        try {
            $registry->register(
                $moduleKey,
                $taskType,
                $sourceType,
                $sourceId
            );

            $this->fail(
                'An unsupported registration should be rejected.'
            );
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('core_tasks', 0);
        }
    }
}
