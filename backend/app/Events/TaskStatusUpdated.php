<?php

namespace App\Events;

use App\Models\Task;

class TaskStatusUpdated
{
    public function __construct(public readonly Task $task)
    {
    }
}
