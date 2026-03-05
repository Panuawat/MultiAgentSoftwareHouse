<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    protected $fillable = [
        'project_id',
        'base_task_id',
        'title',
        'description',
        'status',
        'token_budget',
        'token_used',
        'retry_count',
        'agent_output',
        'pm_review_enabled',
        'pm_messages',
    ];

    protected $casts = [
        'agent_output'      => 'array',
        'token_budget'      => 'integer',
        'token_used'        => 'integer',
        'retry_count'       => 'integer',
        'pm_review_enabled' => 'boolean',
        'pm_messages'       => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function baseTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'base_task_id');
    }

    public function agentLogs(): HasMany
    {
        return $this->hasMany(AgentLog::class);
    }

    public function codeArtifacts(): HasMany
    {
        return $this->hasMany(CodeArtifact::class);
    }

    public function escalate(string $reason): void
    {
        DB::transaction(function () use ($reason) {
            $task = Task::where('id', $this->id)->lockForUpdate()->firstOrFail();
            $task->status = 'human_review_required';
            $task->agent_output = array_merge($task->agent_output ?? [], [
                'escalation_reason' => $reason,
                'escalated_at' => now()->toISOString(),
            ]);
            $task->save();
        });

        // Refresh the current model instance so callers see the updated state
        $this->refresh();
    }
}
