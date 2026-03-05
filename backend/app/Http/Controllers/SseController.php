<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    private const TERMINAL_STATUSES = ['completed', 'human_review_required', 'cancelled'];
    private const CHECK_INTERVAL_US = 200_000;   // 200ms — file check
    private const HEARTBEAT_INTERVAL = 75;        // 75 × 200ms = 15s between heartbeats
    private const MAX_ITERATIONS = 1500;           // 1500 × 200ms = 5 minutes max

    public function stream(Task $task, Request $request): StreamedResponse
    {
        return response()->stream(function () use ($task) {
            set_time_limit(330); // slightly above max duration

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $signalPath = storage_path("app/sse/task-{$task->id}.json");
            $lastVersion = 0;
            $heartbeatCounter = 0;

            // Send initial state immediately from DB so client is never stale
            $task->refresh();
            $this->send(json_encode($task));

            if (in_array($task->status, self::TERMINAL_STATUSES, true)) {
                $this->send(json_encode(['event' => 'close']));
                return;
            }

            for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
                if (connection_aborted()) {
                    break;
                }

                // Check signal file written by BroadcastTaskUpdate listener
                clearstatcache(true, $signalPath);
                if (is_file($signalPath)) {
                    $raw = @file_get_contents($signalPath);
                    if ($raw !== false) {
                        $payload = json_decode($raw, true);
                        $version = $payload['_signal_version'] ?? 0;

                        if ($version > $lastVersion) {
                            $lastVersion = $version;

                            // Strip internal signal field before sending to client
                            unset($payload['_signal_version']);
                            $this->send(json_encode($payload));

                            $status = $payload['status'] ?? '';
                            if (in_array($status, self::TERMINAL_STATUSES, true)) {
                                $this->send(json_encode(['event' => 'close']));
                                break;
                            }

                            $heartbeatCounter = 0; // reset after real message
                        }
                    }
                }

                // Heartbeat to keep connection alive and detect disconnects
                $heartbeatCounter++;
                if ($heartbeatCounter >= self::HEARTBEAT_INTERVAL) {
                    echo ": heartbeat\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $heartbeatCounter = 0;
                }

                usleep(self::CHECK_INTERVAL_US);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function send(string $data): void
    {
        echo "data: {$data}\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
