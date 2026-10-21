<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use HasFactory;

    public const RUNNER_STATUS_NOT_RUNNER = 'NOT_RUNNER';

    public const RUNNER_STATUS_PENDING = 'PENDING';

    public const RUNNER_STATUS_APPROVED = 'APPROVED';

    public const RUNNER_STATUS_REJECTED = 'REJECTED';

    public const RUNNER_STATUS_SUSPENDED = 'SUSPENDED';

    protected $table = 'accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'job_title',
        'phone',
        'email',
        'phone_verified_at',
        'email_verified_at',
        'password',
        'profile_picture',
        'role',
        'status',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * @return HasOne<IdentityVerification, $this>
     */
    public function identityVerification(): HasOne
    {
        return $this->hasOne(IdentityVerification::class);
    }
}
