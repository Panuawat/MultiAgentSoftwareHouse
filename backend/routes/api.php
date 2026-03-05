<?php

use App\Http\Controllers\AgentLogController;
use App\Http\Controllers\CodeArtifactController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\SseController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::apiResource('projects', ProjectController::class);
Route::get('projects/{project}/tasks', [TaskController::class, 'indexByProject']);

Route::get('tasks', [TaskController::class, 'index']);
Route::post('tasks', [TaskController::class, 'store']);
Route::get('tasks/{task}', [TaskController::class, 'show']);
Route::post('tasks/{task}/start', [TaskController::class, 'start']);
Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel']);
Route::post('tasks/{task}/resume', [TaskController::class, 'resume']);
Route::post('tasks/{task}/pm-chat', [TaskController::class, 'pmChat']);
Route::post('tasks/{task}/pm-approve', [TaskController::class, 'pmApprove']);
Route::get('tasks/{task}/logs', [AgentLogController::class, 'index']);
Route::get('tasks/{task}/artifacts', [CodeArtifactController::class, 'index']);
Route::get('tasks/{task}/artifacts/versions', [CodeArtifactController::class, 'versions']);
Route::get('tasks/{task}/export', [CodeArtifactController::class, 'export']);

Route::get('prompts', [PromptController::class, 'index']);
Route::put('prompts/{agent}', [PromptController::class, 'update']);

Route::get('sse/tasks/{task}', [SseController::class, 'stream'])->withoutMiddleware(['api']);

Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware(['throttle:api']);
