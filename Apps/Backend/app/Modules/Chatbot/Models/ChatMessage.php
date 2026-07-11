<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Models;

use App\Modules\Chatbot\Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $thread_id
 * @property string $role 'user' | 'assistant'
 * @property string $content
 * @property array<int, array<string, mixed>>|null $tool_calls
 */
#[Fillable(['thread_id', 'role', 'content', 'tool_calls'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    protected static function newFactory(): Factory
    {
        return ChatMessageFactory::new();
    }
}
