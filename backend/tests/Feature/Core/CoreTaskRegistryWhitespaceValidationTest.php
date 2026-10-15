<?php

namespace Tests\Feature\Core;

use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoreTaskRegistryWhitespaceValidationTest extends TestCase
{
    use RefreshDatabase;

    public static function whitespaceIdentityFieldsProvider(): array
    {
        return [
            'whitespace module key' => [
                '   ',
                'PICK_AND_DROP_REQUEST',
                'pick_and_drop_request',
                'source-whitespace-module',
            ],
            'whitespace task type' => [
                'PickAndDrop',
                " \t ",
                'pick_and_drop_request',
                'source-whitespace-type',
            ],
            'whitespace source type' => [
                'PickAndDrop',
                'PICK_AND_DROP_REQUEST',
                " \t ",
                'source-whitespace-type-value',
            ],
            'whitespace source id' => [
                'PickAndDrop',
                'PICK_AND_DROP_REQUEST',
                'pick_and_drop_request',
                " \t ",
            ],
        ];
    }

    #[DataProvider('whitespaceIdentityFieldsProvider')]
    public function test_whitespace_only_identity_fields_are_rejected(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId
    ): void {
        $registry = new CoreTaskRegistryService();

        $this->expectException(InvalidArgumentException::class);

        try {
            $registry->register(
                $moduleKey,
                $taskType,
                $sourceType,
                $sourceId
            );
        } finally {
            $this->assertDatabaseCount('core_tasks', 0);
        }
    }
}
