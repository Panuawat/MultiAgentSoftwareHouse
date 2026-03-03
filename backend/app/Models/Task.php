<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'token_budget',
        'token_used',
        'retry_count',
        'agent_output',
    ];

    protected $casts = [
        'agent_output' => 'array',
        'token_budget' => 'integer',
        'token_used' => 'integer',
        'retry_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
        $this->status = 'human_review_required';
        $this->agent_output = array_merge($this->agent_output ?? [], [
            'escalation_reason' => $reason,
            'escalated_at' => now()->toISOString(),
        ]);
        $this->save();
    }
}
