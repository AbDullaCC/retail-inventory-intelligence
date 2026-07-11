<?php

declare(strict_types=1);

use App\Modules\Chatbot\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('chat/threads', [ChatController::class, 'threads']);
    Route::get('chat/threads/{thread}', [ChatController::class, 'thread'])->whereNumber('thread');
    Route::post('chat/messages', [ChatController::class, 'send']);
});
