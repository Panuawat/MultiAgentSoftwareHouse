<?php

use App\Http\Controllers\AgentLogController;
use App\Http\Controllers\CodeArtifactController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SseController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::apiResource('projects', ProjectController::class);
Route::get('projects/{project}/tasks', [TaskController::class, 'indexByProject']);

Route::get('tasks', [TaskController::class, 'index']);
Route::post('tasks', [TaskController::class, 'store']);
Route::get('tasks/{task}', [TaskController::class, 'show']);
Route::post('tasks/{task}/start', [TaskController::class, 'start']);
Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel']);
Route::post('tasks/{task}/resume', [TaskController::class, 'resume']);
Route::get('tasks/{task}/logs', [AgentLogController::class, 'index']);
Route::get('tasks/{task}/artifacts', [CodeArtifactController::class, 'index']);
Route::get('tasks/{task}/export', [CodeArtifactController::class, 'export']);

Route::get('sse/tasks/{task}', [SseController::class, 'stream'])->withoutMiddleware(['api']);
