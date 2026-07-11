<?php

namespace App\Modules\Chatbot\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbot\Requests\SendMessageRequest;
use App\Modules\Chatbot\Services\Contracts\ChatServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class ChatController extends Controller
{
    public function __construct(
        private readonly ChatServiceInterface $chat,
    ) {}

    public function threads(): JsonResponse
    {
        return ApiResponse::collection(
            array_map(static fn ($thread) => $thread->toArray(), $this->chat->threads((int) Auth::id())),
        );
    }

    public function thread(int $thread): JsonResponse
    {
        return ApiResponse::item($this->chat->thread((int) Auth::id(), $thread));
    }

    public function send(SendMessageRequest $request): JsonResponse
    {
        // The tool loop may make several sequential LLM round-trips — don't
        // let PHP's execution limit kill the request halfway through.
        set_time_limit(0);

        return ApiResponse::item(
            $this->chat->send((int) Auth::id(), $request->threadId(), $request->message()),
        );
    }
}
