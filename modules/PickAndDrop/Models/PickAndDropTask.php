<?php

namespace Modules\PickAndDrop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $request_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
