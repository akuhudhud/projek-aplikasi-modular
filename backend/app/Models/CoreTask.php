<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreTask extends Model
{
    public const STATUS_OPEN = 'OPEN';

    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'core_tasks';

    protected $primaryKey = 'task_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'task_id',
        'module_key',
        'task_type',
        'source_type',
        'source_id',
        'created_by_account_id',
        'assigned_account_id',
        'generic_status',
        'assigned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
