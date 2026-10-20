<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerificationDocument extends Model
{
    protected $table = 'identity_verification_documents';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'identity_verification_id',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected $hidden = [
        'disk',
        'path',
    ];

    /**
     * @return BelongsTo<IdentityVerification, $this>
     */
    public function identityVerification(): BelongsTo
    {
        return $this->belongsTo(IdentityVerification::class);
    }
}
