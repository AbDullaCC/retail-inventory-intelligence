<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Database\Factories;

use App\Modules\Chatbot\Models\ChatMessage;
use App\Modules\Chatbot\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => ChatThread::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->sentence(),
            'tool_calls' => null,
        ];
    }
}
