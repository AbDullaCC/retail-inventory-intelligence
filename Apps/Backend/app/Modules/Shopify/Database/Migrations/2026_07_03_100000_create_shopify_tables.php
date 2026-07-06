<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connector state for the Shopify integration:
 * - shopify_product_maps ties a local product to its Shopify variant so
 *   re-syncs are idempotent (one local product per variant).
 * - shopify_sync_states is a single row holding the order-sync watermark;
 *   null means the initial history backfill has not run yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_product_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shopify_product_id', 64);
            $table->string('shopify_variant_id', 64)->unique();
            $table->string('shopify_inventory_item_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('shopify_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('orders_synced_until')->nullable();
            $table->dateTime('products_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_product_maps');
        Schema::dropIfExists('shopify_sync_states');
    }
};
