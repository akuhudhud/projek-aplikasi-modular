<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class RunnerLicenseVerification extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'runner_license_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'account_id',
        'license_number_encrypted',
        'license_class',
        'expires_at',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewer_account_id',
        'rejection_reason',
        'disk',
        'document_path',
        'mime_type',
        'size_bytes',
    ];

    protected $hidden = [
        'license_number_encrypted',
        'document_path',
        'disk',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function decryptLicenseNumber(): string
    {
        return Crypt::decryptString(
            $this->license_number_encrypted
        );
    }
}
