<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLog extends Model
{
    protected $fillable = [
        'task_id',
        'agent_type',
        'input',
        'output',
        'tokens_used',
        'status',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'tokens_used' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
