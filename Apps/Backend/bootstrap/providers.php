<?php

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Category\Providers\CategoryServiceProvider;
use App\Modules\Chatbot\Providers\ChatbotServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Forecast\Providers\ForecastServiceProvider;
use App\Modules\Intelligence\Providers\IntelligenceServiceProvider;
use App\Modules\Product\Providers\ProductServiceProvider;
use App\Modules\Shopify\Providers\ShopifyServiceProvider;
use App\Modules\Stock\Providers\StockServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    CategoryServiceProvider::class,
    ProductServiceProvider::class,
    StockServiceProvider::class,
    DashboardServiceProvider::class,
    ShopifyServiceProvider::class,
    ForecastServiceProvider::class,
    IntelligenceServiceProvider::class,
    ChatbotServiceProvider::class,
];
