<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sessions';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'account_id',
        'token_hash',
        'created_at',
        'ended_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
