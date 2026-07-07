<?php

declare(strict_types=1);

namespace App\Modules\Shopify\Support;

use App\Modules\Shopify\Exceptions\ShopifyUnavailableException;
use App\Modules\Shopify\Models\ShopifyConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin transport for Shopify's GraphQL Admin API (the only Admin API open to
 * new apps since 2025). Credentials come from the UI-stored connection first,
 * falling back to the SHOPIFY_* env vars for CLI-only setups. Handles auth
 * headers and Shopify's cost-based rate limiting: a THROTTLED response is
 * retried with backoff rather than failing the sync.
 */
final class ShopifyClient
{
    private const MAX_ATTEMPTS = 4;

    private const SHOP_QUERY = 'query { shop { name } }';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    public function domain(): string
    {
        return (string) ($this->credentials()['domain'] ?? '');
    }

    /**
     * Validate a domain + token pair with a live call; returns the shop name.
     * Throws ShopifyUnavailableException for unreachable stores; any other
     * RuntimeException means the credentials were rejected.
     */
    public function verify(string $domain, string $token): string
    {
        try {
            $data = $this->execute($domain, $token, self::SHOP_QUERY, []);
        } catch (RequestException $e) {
            // 401/403/404 from Shopify — wrong token or wrong store domain.
            throw new RuntimeException('Shopify rejected the request (HTTP '.$e->response->status().').', 0, $e);
        }

        $name = $data['shop']['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Shopify did not return shop details — check the token scopes.');
        }

        return $name;
    }

    /**
     * Execute a GraphQL query against the connected store and return `data`.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            throw ShopifyUnavailableException::notConfigured();
        }

        return $this->execute($credentials['domain'], $credentials['token'], $query, $variables);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function execute(string $domain, string $token, string $query, array $variables): array
    {
        $settings = config('services.shopify');
        $url = sprintf('https://%s/admin/api/%s/graphql.json', $domain, $settings['version']);

        // GraphQL `variables` must be a JSON object; an empty PHP array would
        // serialise to [] and Shopify rejects it — omit the key instead.
        $payload = ['query' => $query];
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
                    ->timeout((int) $settings['timeout'])
                    ->acceptJson()
                    ->post($url, $payload)
                    ->throw()
                    ->json();
            } catch (ConnectionException $e) {
                throw ShopifyUnavailableException::at($domain, $e);
            }

            $errors = $response['errors'] ?? [];
            if ($errors === []) {
                return $response['data'] ?? [];
            }

            if ($this->isThrottled($errors) && $attempt < self::MAX_ATTEMPTS) {
                usleep($attempt * (int) $settings['throttle_delay_ms'] * 1000);

                continue;
            }

            throw new RuntimeException('Shopify GraphQL error: '.json_encode($errors));
        }

        throw new RuntimeException('Shopify GraphQL error: retries exhausted.');
    }

    /**
     * UI-stored connection wins; env vars are the CLI fallback.
     *
     * @return array{domain: string, token: string}|null
     */
    private function credentials(): ?array
    {
        $connection = ShopifyConnection::current();
        if ($connection !== null) {
            return ['domain' => $connection->domain, 'token' => $connection->token];
        }

        $settings = config('services.shopify');
        $domain = trim((string) ($settings['domain'] ?? ''));
        $token = trim((string) ($settings['token'] ?? ''));
        if ($domain !== '' && $token !== '') {
            return ['domain' => $domain, 'token' => $token];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function isThrottled(array $errors): bool
    {
        foreach ($errors as $error) {
            if (($error['extensions']['code'] ?? '') === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }
}
