<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceRequest extends Model
{
    public const STATUS_OPEN = 'OPEN';

    public const STATUS_AWARDED = 'AWARDED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const SOURCE_MARKETPLACE = 'marketplace';

    public const SOURCE_RUNNER_PROFILE_LINK = 'runner_profile_link';

    protected $table = 'marketplace_requests';

    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'pickup_location_snapshot' => 'array',
            'delivery_location_snapshot' => 'array',
            'expires_at' => 'datetime',
            'awarded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'customer_account_id',
            'id'
        );
    }

    public function targetRunner(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'target_runner_account_id',
            'id'
        );
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(
            MarketplaceQuotation::class,
            'request_id',
            'request_id'
        );
    }

    public function winningQuotation(): BelongsTo
    {
        return $this->belongsTo(
            MarketplaceQuotation::class,
            'winning_quotation_id',
            'quotation_id'
        );
    }
}
