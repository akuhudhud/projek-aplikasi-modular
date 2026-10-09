<?php

namespace Modules\PickAndDrop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickAndDropTask extends Model
{
    protected $table = 'pick_and_drop_tasks';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'request_id',
        'status',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            PickAndDropRequest::class,
            'request_id'
        );
    }
}
