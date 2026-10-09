<?php

namespace Modules\PickAndDrop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PickAndDropRequest extends Model
{
    protected $table = 'pick_and_drop_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'customer_account_id',
        'category',
        'item_type',
        'item_description',
        'pickup_location_snapshot',
        'pickup_contact_name',
        'pickup_contact_phone',
        'delivery_location_snapshot',
        'recipient_name',
        'recipient_phone',
        'additional_instructions',
        'status',
    ];

    protected $casts = [
        'pickup_location_snapshot' => 'array',
        'delivery_location_snapshot' => 'array',
    ];

    public function task(): HasOne
    {
        return $this->hasOne(
            PickAndDropTask::class,
            'request_id'
        );
    }
}
