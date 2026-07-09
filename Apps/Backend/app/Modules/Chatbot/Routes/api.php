<?php

declare(strict_types=1);

use App\Modules\Chatbot\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('chat/threads', [ChatbotController::class, 'index']);
    Route::post('chat/threads', [ChatbotController::class, 'store']);
    Route::get('chat/threads/{thread}', [ChatbotController::class, 'show'])->whereNumber('thread');
    Route::post('chat/messages', [ChatbotController::class, 'send']);
});
