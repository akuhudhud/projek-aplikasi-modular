<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminPushSubscription extends Model
{
    protected $table = 'admin_push_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'account_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'last_used_at',
    ];

    protected $hidden = [
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (empty($subscription->id)) {
                $subscription->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
