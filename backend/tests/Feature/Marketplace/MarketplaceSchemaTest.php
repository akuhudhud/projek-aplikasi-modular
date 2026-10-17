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
            'service_type',
            'description',
            'pickup_location_snapshot',
            'delivery_location_snapshot',
            'status',
            'winning_quotation_id',
            'expires_at',
            'awarded_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]));

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
}
