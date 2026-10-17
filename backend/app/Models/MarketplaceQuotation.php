<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceQuotation extends Model
{
    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_NOT_SELECTED = 'NOT_SELECTED';

    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public const STATUS_EXPIRED = 'EXPIRED';

    protected $table = 'marketplace_quotations';

    protected $primaryKey = 'quotation_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            MarketplaceRequest::class,
            'request_id',
            'request_id'
        );
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'runner_account_id',
            'id'
        );
    }
}
