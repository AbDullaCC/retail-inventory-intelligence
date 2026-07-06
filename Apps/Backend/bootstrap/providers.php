<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Category\Providers\CategoryServiceProvider::class,
    App\Modules\Product\Providers\ProductServiceProvider::class,
    App\Modules\Stock\Providers\StockServiceProvider::class,
    App\Modules\Dashboard\Providers\DashboardServiceProvider::class,
    App\Modules\Shopify\Providers\ShopifyServiceProvider::class,
    App\Modules\Forecast\Providers\ForecastServiceProvider::class,
    App\Modules\Intelligence\Providers\IntelligenceServiceProvider::class,
];
