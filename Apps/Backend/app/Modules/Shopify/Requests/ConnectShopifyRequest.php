<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectShopifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $domain = strtolower(trim((string) $this->input('domain', '')));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        $this->merge(['domain' => $domain, 'token' => trim((string) $this->input('token', ''))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9\-.]*\.[a-z]{2,}$/'],
            'token' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'Enter the store domain like your-store.myshopify.com (without https://).',
        ];
    }

    public function domain(): string
    {
        return (string) $this->validated('domain');
    }

    public function token(): string
    {
        return (string) $this->validated('token');
    }
}
