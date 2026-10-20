<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class IdentityVerification extends Model
{
    public const STATUS_NOT_SUBMITTED = 'NOT_SUBMITTED';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'identity_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'account_id',
        'real_name',
        'mykad_number_encrypted',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewer_account_id',
        'rejection_reason',
    ];

    protected $hidden = [
        'mykad_number_encrypted',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<IdentityVerificationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(IdentityVerificationDocument::class);
    }

    /**
     * Return the decrypted MyKad number only for explicitly authorized
     * internal use. Never serialize this value into an API response.
     */
    public function decryptMykadNumber(): string
    {
        return Crypt::decryptString($this->mykad_number_encrypted);
    }
}
