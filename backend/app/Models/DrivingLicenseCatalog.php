<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrivingLicenseCatalog extends Model
{
    protected $table = 'driving_license_catalog';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'driving_license_type_id',
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

    public function licenseType(): BelongsTo
    {
        return $this->belongsTo(
            DrivingLicenseType::class,
            'driving_license_type_id'
        );
    }
}
