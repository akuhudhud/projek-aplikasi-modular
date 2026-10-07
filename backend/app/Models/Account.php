<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
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

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }
}
