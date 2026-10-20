<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrivingLicenseType extends Model
{
    protected $table = 'driving_license_types';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(
            DrivingLicenseCatalog::class,
            'driving_license_type_id'
        );
    }
}
