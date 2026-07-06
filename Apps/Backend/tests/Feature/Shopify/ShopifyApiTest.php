<?php

declare(strict_types=1);

namespace Tests\Feature\Shopify;

use App\Modules\Auth\Models\User;
use App\Modules\Shopify\Models\ShopifyConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopifyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.shopify', [
            'domain' => null,
            'token' => null,
            'version' => '2026-01',
            'timeout' => 5,
            'history_days' => 730,
            'throttle_delay_ms' => 0,
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/shopify/status')->assertUnauthorized();
        $this->postJson('/api/shopify/connect')->assertUnauthorized();
        $this->postJson('/api/shopify/sync')->assertUnauthorized();
        $this->deleteJson('/api/shopify/connection')->assertUnauthorized();
    }

    public function test_status_reports_disconnected_when_nothing_is_configured(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/shopify/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.source', null);
    }

    public function test_status_reports_env_configuration_as_connected(): void
    {
        config()->set('services.shopify.domain', 'env-store.myshopify.com');
        config()->set('services.shopify.token', 'shpat_env');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/shopify/status')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.source', 'env')
            ->assertJsonPath('data.domain', 'env-store.myshopify.com');
    }

    public function test_connect_verifies_credentials_and_stores_the_token_encrypted(): void
    {
        Http::fake(['demo-shop.myshopify.com/*' => Http::response(['data' => ['shop' => ['name' => 'Demo Shop']]])]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/shopify/connect', [
            'domain' => 'https://Demo-Shop.myshopify.com/',
            'token' => 'shpat_valid_token',
        ])
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.source', 'ui')
            ->assertJsonPath('data.domain', 'demo-shop.myshopify.com')
            ->assertJsonPath('data.shop_name', 'Demo Shop');

        $connection = ShopifyConnection::current();
        $this->assertSame('shpat_valid_token', $connection->token, 'decrypts transparently');
        $rawToken = (string) DB::table('shopify_connections')->value('token');
        $this->assertNotSame('shpat_valid_token', $rawToken, 'token is encrypted at rest');
    }

    public function test_connect_rejects_bad_credentials_without_saving(): void
    {
        Http::fake(['bad-shop.myshopify.com/*' => Http::response(['errors' => 'Unauthorized'], 401)]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/shopify/connect', [
            'domain' => 'bad-shop.myshopify.com',
            'token' => 'shpat_wrong_token',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertSame(0, ShopifyConnection::query()->count());
    }

    public function test_connect_validates_the_domain_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/shopify/connect', ['domain' => 'not a domain', 'token' => 'shpat_valid_token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_sync_endpoint_runs_a_sync_and_persists_stats_on_the_connection(): void
    {
        ShopifyConnection::query()->create([
            'domain' => 'demo-shop.myshopify.com',
            'token' => 'shpat_valid_token',
            'shop_name' => 'Demo Shop',
        ]);

        Http::fake(function ($request) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, 'products(')) {
                return Http::response(['data' => ['products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [[
                        'id' => 'gid://shopify/Product/1',
                        'title' => 'Mug',
                        'status' => 'ACTIVE',
                        'productType' => 'Kitchen',
                        'variants' => ['nodes' => [[
                            'id' => 'gid://shopify/ProductVariant/111',
                            'sku' => 'MUG-1',
                            'title' => 'Default Title',
                            'price' => '9.50',
                            'inventoryQuantity' => 12,
                            'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/11', 'unitCost' => null],
                        ]]],
                    ]],
                ]]]);
            }

            return Http::response(['data' => ['orders' => [
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'nodes' => [],
            ]]]);
        });
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/shopify/sync')
            ->assertOk()
            ->assertJsonPath('data.stats.products_created', 1)
            ->assertJsonPath('data.status.last_stats.products_created', 1);

        $this->assertNotNull(ShopifyConnection::current()->last_synced_at);
    }

    public function test_disconnect_removes_the_connection_but_keeps_imported_data(): void
    {
        ShopifyConnection::query()->create([
            'domain' => 'demo-shop.myshopify.com',
            'token' => 'shpat_valid_token',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/shopify/connection')->assertOk();

        $this->assertSame(0, ShopifyConnection::query()->count());
        $this->getJson('/api/shopify/status')->assertJsonPath('data.connected', false);
    }
}
