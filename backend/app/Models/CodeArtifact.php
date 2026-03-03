<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeArtifact extends Model
{
    protected $fillable = [
        'task_id',
        'filename',
        'content',
        'version',
        'artifact_type',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
