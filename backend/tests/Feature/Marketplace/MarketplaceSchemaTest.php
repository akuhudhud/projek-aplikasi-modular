<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_tables_contain_required_fields(): void
    {
        $this->assertTrue(Schema::hasTable('marketplace_requests'));

        $this->assertTrue(Schema::hasColumns('marketplace_requests', [
            'request_id',
            'customer_account_id',
            'target_runner_account_id',
            'source_type',
            'task_class',
            'description',
            'pickup_location_snapshot',
            'delivery_location_snapshot',
            'task_location_snapshot',
            'status',
            'winning_quotation_id',
            'expires_at',
            'awarded_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(
            Schema::hasColumn('marketplace_requests', 'service_type')
        );

        $this->assertTrue(Schema::hasTable('marketplace_quotations'));

        $this->assertTrue(Schema::hasColumns('marketplace_quotations', [
            'quotation_id',
            'request_id',
            'runner_account_id',
            'amount',
            'currency',
            'message',
            'status',
            'expires_at',
            'accepted_at',
            'withdrawn_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_request_and_quotation_models_use_uuid_primary_keys(): void
    {
        $request = new MarketplaceRequest();
        $quotation = new MarketplaceQuotation();

        $this->assertSame('marketplace_requests', $request->getTable());
        $this->assertSame('request_id', $request->getKeyName());
        $this->assertSame('string', $request->getKeyType());
        $this->assertFalse($request->getIncrementing());

        $this->assertSame('marketplace_quotations', $quotation->getTable());
        $this->assertSame('quotation_id', $quotation->getKeyName());
        $this->assertSame('string', $quotation->getKeyType());
        $this->assertFalse($quotation->getIncrementing());
    }

    public function test_request_model_supports_task_class_and_location_snapshots(): void
    {
        $request = new MarketplaceRequest();

        $this->assertContains('task_class', $request->getFillable());

        $this->assertContains(
            'task_location_snapshot',
            $request->getFillable()
        );

        $this->assertSame(
            'array',
            $request->getCasts()['task_location_snapshot']
        );

        $this->assertSame(
            'array',
            $request->getCasts()['pickup_location_snapshot']
        );

        $this->assertSame(
            'array',
            $request->getCasts()['delivery_location_snapshot']
        );

        $this->assertSame(
            'PICKUP_DELIVERY',
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY
        );

        $this->assertSame(
            'NON_DELIVERY',
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY
        );
    }

    public function test_marketplace_lifecycle_constants_are_defined(): void
    {
        $this->assertSame('OPEN', MarketplaceRequest::STATUS_OPEN);
        $this->assertSame('AWARDED', MarketplaceRequest::STATUS_AWARDED);
        $this->assertSame('CANCELLED', MarketplaceRequest::STATUS_CANCELLED);
        $this->assertSame('EXPIRED', MarketplaceRequest::STATUS_EXPIRED);

        $this->assertSame(
            'marketplace',
            MarketplaceRequest::SOURCE_MARKETPLACE
        );

        $this->assertSame(
            'runner_profile_link',
            MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
        );

        $this->assertSame(
            'SUBMITTED',
            MarketplaceQuotation::STATUS_SUBMITTED
        );

        $this->assertSame(
            'ACCEPTED',
            MarketplaceQuotation::STATUS_ACCEPTED
        );

        $this->assertSame(
            'NOT_SELECTED',
            MarketplaceQuotation::STATUS_NOT_SELECTED
        );

        $this->assertSame(
            'WITHDRAWN',
            MarketplaceQuotation::STATUS_WITHDRAWN
        );

        $this->assertSame(
            'EXPIRED',
            MarketplaceQuotation::STATUS_EXPIRED
        );
    }

    public function test_marketplace_foreign_keys_preserve_record_relationships(): void
    {
        $requestForeignKeys = Schema::getForeignKeys(
            'marketplace_requests'
        );

        $quotationForeignKeys = Schema::getForeignKeys(
            'marketplace_quotations'
        );

        $this->assertForeignKey(
            $requestForeignKeys,
            'customer_account_id',
            'accounts',
            'id',
            'restrict'
        );

        $this->assertForeignKey(
            $requestForeignKeys,
            'target_runner_account_id',
            'accounts',
            'id',
            'set null'
        );

        $this->assertForeignKey(
            $requestForeignKeys,
            'winning_quotation_id',
            'marketplace_quotations',
            'quotation_id',
            'restrict'
        );

        $this->assertForeignKey(
            $quotationForeignKeys,
            'request_id',
            'marketplace_requests',
            'request_id',
            'restrict'
        );

        $this->assertForeignKey(
            $quotationForeignKeys,
            'runner_account_id',
            'accounts',
            'id',
            'restrict'
        );
    }

    public function test_core_tasks_table_contains_required_fields(): void
    {
        $this->assertTrue(Schema::hasTable('core_tasks'));

        $this->assertTrue(Schema::hasColumns('core_tasks', [
            'task_id',
            'module_key',
            'task_type',
            'source_type',
            'source_id',
            'created_by_account_id',
            'assigned_account_id',
            'generic_status',
            'assigned_at',
            'started_at',
            'completed_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_core_task_account_foreign_keys_preserve_attribution(): void
    {
        $foreignKeys = Schema::getForeignKeys('core_tasks');

        $this->assertForeignKey(
            $foreignKeys,
            'created_by_account_id',
            'accounts',
            'id',
            'restrict'
        );

        $this->assertForeignKey(
            $foreignKeys,
            'assigned_account_id',
            'accounts',
            'id',
            'restrict'
        );
    }

    /**
     * Assert a foreign key definition returned by Laravel's schema builder.
     *
     * @param array<int, array<string, mixed>> $foreignKeys
     */
    private function assertForeignKey(
        array $foreignKeys,
        string $column,
        string $foreignTable,
        string $foreignColumn,
        string $onDelete
    ): void {
        $matchingForeignKey = collect($foreignKeys)->first(
            fn (array $foreignKey): bool =>
                ($foreignKey['columns'] ?? []) === [$column]
                && ($foreignKey['foreign_table'] ?? null) === $foreignTable
                && ($foreignKey['foreign_columns'] ?? []) === [$foreignColumn]
        );

        $this->assertNotNull(
            $matchingForeignKey,
            sprintf(
                'Foreign key for %s referencing %s.%s was not found.',
                $column,
                $foreignTable,
                $foreignColumn
            )
        );

        $this->assertSame(
            $onDelete,
            strtolower((string) ($matchingForeignKey['on_delete'] ?? '')),
            sprintf(
                'Unexpected ON DELETE rule for foreign key %s.',
                $column
            )
        );
    }
}
