<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The messages within a chat thread. `role` is 'user' (the human prompt) or
 * 'assistant' (the LLM's reply). `tool_calls` is a truncated record of the read
 * tools the assistant invoked to produce its answer — display-only "cited
 * sources"; the full tool payloads are never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->timestamps();

            // Ordered retrieval of a thread's messages.
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
