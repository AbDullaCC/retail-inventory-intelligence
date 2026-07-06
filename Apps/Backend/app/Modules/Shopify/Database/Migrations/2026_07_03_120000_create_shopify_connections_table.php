<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store connection entered through the UI (single row). The admin token is
 * encrypted at rest via the model's `encrypted` cast. When no row exists the
 * connector falls back to the SHOPIFY_* env credentials (CLI-only setups).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('domain');
            $table->text('token');
            $table->string('shop_name')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->json('last_stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_connections');
    }
};
