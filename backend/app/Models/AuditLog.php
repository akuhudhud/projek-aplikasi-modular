<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_account_id',
        'target_account_id',
        'action',
        'reason',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }
}
