<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateThreadRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function title(): ?string
    {
        $title = $this->validated('title');

        return $title === null ? null : (string) $title;
    }
}
