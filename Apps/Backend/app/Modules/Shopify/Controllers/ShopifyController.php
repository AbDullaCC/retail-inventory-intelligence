<?php

namespace App\Modules\Shopify\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Shopify\DTOs\ShopifyStatusDTO;
use App\Modules\Shopify\Exceptions\ShopifyUnavailableException;
use App\Modules\Shopify\Models\ShopifyConnection;
use App\Modules\Shopify\Requests\ConnectShopifyRequest;
use App\Modules\Shopify\Services\Contracts\ShopifySyncServiceInterface;
use App\Modules\Shopify\Support\ShopifyClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ShopifyController extends Controller
{
    public function __construct(
        private readonly ShopifyClient $client,
        private readonly ShopifySyncServiceInterface $syncService,
    ) {}

    public function status(): JsonResponse
    {
        return ApiResponse::item($this->currentStatus());
    }

    public function connect(ConnectShopifyRequest $request): JsonResponse
    {
        try {
            $shopName = $this->client->verify($request->domain(), $request->token());
        } catch (ShopifyUnavailableException $e) {
            throw $e; // unreachable store → 503, not a validation error
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'token' => 'Shopify rejected the credentials — check the store domain and the Admin API token scopes.',
            ]);
        }

        // Single connection: replace whatever was stored before.
        ShopifyConnection::query()->delete();
        ShopifyConnection::query()->create([
            'domain' => $request->domain(),
            'token' => $request->token(),
            'shop_name' => $shopName,
        ]);

        return ApiResponse::item($this->currentStatus(), sprintf('Connected to %s.', $shopName));
    }

    public function sync(): JsonResponse
    {
        // A first-run history backfill can outlive the default execution limit.
        set_time_limit(0);

        $stats = $this->syncService->sync();

        return ApiResponse::item([
            'stats' => $stats,
            'status' => $this->currentStatus()->toArray(),
        ], $stats['backfill'] ? 'Order history imported.' : 'Store synced.');
    }

    public function disconnect(): JsonResponse
    {
        ShopifyConnection::query()->delete();

        return ApiResponse::message('Shopify disconnected. Imported products and history are kept.');
    }

    private function currentStatus(): ShopifyStatusDTO
    {
        $settings = config('services.shopify');
        $envDomain = trim((string) ($settings['domain'] ?? ''));
        $envConfigured = $envDomain !== '' && trim((string) ($settings['token'] ?? '')) !== '';

        return ShopifyStatusDTO::from(ShopifyConnection::current(), $envConfigured, $envConfigured ? $envDomain : null);
    }
}
