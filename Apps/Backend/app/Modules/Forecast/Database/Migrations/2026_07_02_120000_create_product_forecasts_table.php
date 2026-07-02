<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latest model forecast per product, written by forecast:run and read by the
 * Intelligence module and the forecast/chart endpoints. One row per product,
 * replaced on every run — history/audit is served by forecast:evaluate
 * (backtesting), not by archiving forecasts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('generated_at');
            $table->unsignedSmallInteger('horizon_days');
            $table->unsignedSmallInteger('history_days');
            $table->unsignedSmallInteger('lead_time_days');
            $table->string('model_used', 40);
            $table->double('expected_daily_demand');
            $table->double('demand_over_lead_time');
            $table->double('p90_demand_over_lead_time')->nullable();
            $table->double('demand_lead_plus_coverage');
            $table->double('actuals_last_28d');
            $table->json('daily_forecast');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_forecasts');
    }
};
