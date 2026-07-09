<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Chatbot\Database\Factories\ChatThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation thread owned by one user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
#[Fillable(['user_id', 'title', 'last_message_at'])]
class ChatThread extends Model
{
    /** @use HasFactory<ChatThreadFactory> */
    use HasFactory;
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    protected static function newFactory(): Factory
    {
        return ChatThreadFactory::new();
    }
}
