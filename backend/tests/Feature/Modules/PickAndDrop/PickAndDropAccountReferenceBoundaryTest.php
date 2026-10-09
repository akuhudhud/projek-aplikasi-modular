<?php

namespace Tests\Feature\Modules\PickAndDrop;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PickAndDrop\Models\PickAndDropRequest;
use Tests\TestCase;

class PickAndDropAccountReferenceBoundaryTest extends TestCase
{
    public function test_pick_and_drop_request_does_not_define_a_core_account_relationship(): void
    {
        $request = new PickAndDropRequest();

        $this->assertFalse(
            method_exists($request, 'customer'),
            'PickAndDropRequest must not define a direct Core Account relationship.'
        );

        $this->assertTrue(
            method_exists($request, 'task'),
            'The internal Task relationship must remain available.'
        );
    }

    public function test_customer_account_id_remains_a_uuid_attribute(): void
    {
        $accountId = '123e4567-e89b-42d3-a456-426614174000';

        $request = new PickAndDropRequest();

        $request->customer_account_id = $accountId;

        $this->assertSame(
            $accountId,
            $request->customer_account_id
        );

        $this->assertContains(
            'customer_account_id',
            $request->getFillable()
        );
    }

    public function test_pick_and_drop_requests_have_no_foreign_key_to_core_accounts(): void
    {
        $this->assertTrue(
            Schema::hasTable('pick_and_drop_requests'),
            'The pick_and_drop_requests table must exist after migrations.'
        );

        $foreignKeys = Schema::getForeignKeys('pick_and_drop_requests');

        $coreAccountForeignKeys = array_filter(
            $foreignKeys,
            fn (array $foreignKey): bool =>
                ($foreignKey['foreign_table'] ?? null) === 'accounts'
                && in_array(
                    'customer_account_id',
                    $foreignKey['columns'] ?? [],
                    true
                )
        );

        $this->assertSame(
            [],
            array_values($coreAccountForeignKeys),
            'PickAndDrop must not enforce a database foreign key to Core accounts.'
        );
    }

    public function test_internal_task_request_foreign_key_remains_available(): void
    {
        $foreignKeys = Schema::getForeignKeys('pick_and_drop_tasks');

        $internalForeignKeys = array_filter(
            $foreignKeys,
            fn (array $foreignKey): bool =>
                ($foreignKey['foreign_table'] ?? null) === 'pick_and_drop_requests'
                && in_array(
                    'request_id',
                    $foreignKey['columns'] ?? [],
                    true
                )
        );

        $this->assertNotSame(
            [],
            array_values($internalForeignKeys),
            'The internal Task-to-Request foreign key must remain intact.'
        );
    }
}
