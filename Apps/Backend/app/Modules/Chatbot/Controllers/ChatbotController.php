<?php

namespace App\Modules\Chatbot\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbot\Requests\CreateThreadRequest;
use App\Modules\Chatbot\Requests\SendMessageRequest;
use App\Modules\Chatbot\Services\Contracts\ChatbotServiceInterface;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotServiceInterface $service,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::collection(
            array_map(fn ($t) => $t->toArray(), $this->service->threads((int) Auth::id())),
        );
    }

    public function store(CreateThreadRequest $request): JsonResponse
    {
        return ApiResponse::item(
            $this->service->createThread((int) Auth::id(), $request->title()),
            'Thread created.',
            201,
        );
    }

    public function show(int $thread): JsonResponse
    {
        return ApiResponse::item($this->service->thread((int) Auth::id(), $thread));
    }

    public function send(SendMessageRequest $request): JsonResponse
    {
        // The tool loop can make up to 5 sequential LLM round-trips; under
        // Apache/FPM that can outrun PHP's default execution limit.
        set_time_limit(0);

        return ApiResponse::item(
            $this->service->ask((int) Auth::id(), $request->threadId(), $request->message()),
        );
    }
}
