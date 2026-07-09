<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an assistant message submission. `thread_id` is intentionally NOT
 * given an `exists:` rule — a 422-vs-404 split would leak which thread ids exist
 * globally. The service's ownership check 404s uniformly for missing and
 * foreign threads.
 */
final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'thread_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }

    public function threadId(): ?int
    {
        $id = $this->validated('thread_id');

        return $id === null ? null : (int) $id;
    }
}
