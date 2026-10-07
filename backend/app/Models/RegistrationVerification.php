<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationVerification extends Model
{
    use HasFactory;

    protected $table = 'registration_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'channel',
        'contact',
        'purpose',
        'otp_hash',
        'expires_at',
        'attempts',
        'verified_at',
        'verification_token_hash',
        'token_expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'otp_hash',
        'verification_token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
