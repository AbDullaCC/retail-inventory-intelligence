<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DashboardTrendsRequest extends FormRequest
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
            'days' => ['sometimes', 'integer', 'min:7', 'max:90'],
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
        ];
    }

    public function days(): int
    {
        return (int) $this->validated('days', 30);
    }

    public function productId(): ?int
    {
        $id = $this->validated('product_id');

        return $id === null ? null : (int) $id;
    }
}
