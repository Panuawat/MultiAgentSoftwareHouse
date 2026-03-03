<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    private const TERMINAL_STATUSES = ['completed', 'human_review_required', 'cancelled'];

    public function stream(Task $task, Request $request): StreamedResponse
    {
        return response()->stream(function () use ($task) {
            set_time_limit(300);

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $maxIterations = 120;

            for ($i = 0; $i < $maxIterations; $i++) {
                $task->refresh();

                echo 'data: '.json_encode($task)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                if (in_array($task->status, self::TERMINAL_STATUSES, true)) {
                    echo 'data: '.json_encode(['event' => 'close'])."\n\n";
                    flush();
                    break;
                }

                usleep(1_500_000); // 1.5 seconds
            }

        }, 200, [
            'Content-Type'    => 'text/event-stream',
            'Cache-Control'   => 'no-cache',
            'Connection'      => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
