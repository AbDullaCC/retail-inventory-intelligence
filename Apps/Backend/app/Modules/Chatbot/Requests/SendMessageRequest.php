<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A chat message submission. `thread_id` gets no `exists:` rule on purpose:
 * a 422-vs-404 difference would reveal which thread ids exist globally — the
 * service's ownership check 404s uniformly instead.
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
            'thread_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function message(): string
    {
        return trim((string) $this->validated('message'));
    }

    public function threadId(): ?int
    {
        $id = $this->validated('thread_id');

        return $id === null ? null : (int) $id;
    }
}
