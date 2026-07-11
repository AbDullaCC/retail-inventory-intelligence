<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Chatbot\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(4), '.'),
            'last_message_at' => now(),
        ];
    }
}
