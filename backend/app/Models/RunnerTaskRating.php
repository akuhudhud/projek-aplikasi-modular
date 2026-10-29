<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunnerTaskRating extends Model
{
    protected $table = 'runner_task_ratings';

    protected $primaryKey = 'rating_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'rating_id',
        'task_id',
        'customer_account_id',
        'runner_account_id',
        'rating',
        'review',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Core Task yang dinilai.
     *
     * @return BelongsTo<CoreTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(
            CoreTask::class,
            'task_id',
            'task_id'
        );
    }

    /**
     * Pelanggan yang memiliki penilaian.
     *
     * @return BelongsTo<Account, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'customer_account_id',
            'id'
        );
    }

    /**
     * Runner yang dinilai.
     *
     * @return BelongsTo<Account, $this>
     */
    public function runner(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'runner_account_id',
            'id'
        );
    }
}
