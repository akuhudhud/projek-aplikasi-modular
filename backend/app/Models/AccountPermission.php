<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPermission extends Model
{
    protected $table = 'account_permissions';

    protected $fillable = [
        'account_id',
        'permission_key',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
