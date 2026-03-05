<?php

namespace App\Listeners;

use App\Events\TaskStatusUpdated;
use Illuminate\Support\Facades\Log;

class BroadcastTaskUpdate
{
    public function handle(TaskStatusUpdated $event): void
    {
        $task = $event->task;
        $path = storage_path("app/sse/task-{$task->id}.json");

        $payload = array_merge($task->toArray(), [
            '_signal_version' => hrtime(true), // nanosecond precision
        ]);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($payload), LOCK_EX);
    }
}
