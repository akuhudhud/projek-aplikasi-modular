<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunnerPublicProfile extends Model
{
    protected $table = 'runner_public_profiles';

    protected $primaryKey = 'profile_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'profile_id',
        'account_id',
        'public_slug',
        'display_name',
        'bio',
        'service_area',
        'base_location',
        'coverage_area',
        'services',
        'is_published',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'is_published' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'account_id',
            'id'
        );
    }
}
