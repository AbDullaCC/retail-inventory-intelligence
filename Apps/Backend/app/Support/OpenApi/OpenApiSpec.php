<?php

declare(strict_types=1);

namespace App\Support\OpenApi;

/**
 * Hand-authored OpenAPI 3.0 description of the Retail Inventory API.
 *
 * Kept spec-first (rather than generated from annotations) so the contract is
 * reviewable in one place; it mirrors the DTOs the API actually returns and the
 * behaviour covered by the test suite.
 */
final class OpenApiSpec
{
    public static function build(string $serverUrl = '/'): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Retail Inventory API',
                'description' => 'REST API for the modular Laravel retail inventory backend. '
                    .'Authenticate via `/api/auth/login` (or `/api/auth/register`) to obtain a bearer token, '
                    .'then click **Authorize** and paste the token.',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => rtrim($serverUrl, '/'), 'description' => 'This server'],
            ],
            'tags' => [
                ['name' => 'Auth', 'description' => 'Registration, login and the current user.'],
                ['name' => 'Dashboard', 'description' => 'Inventory KPIs.'],
                ['name' => 'Categories', 'description' => 'Product categories.'],
                ['name' => 'Products', 'description' => 'Catalogue and stock levels.'],
                ['name' => 'Stock', 'description' => 'Stock movements (the inventory ledger).'],
                ['name' => 'Intelligence', 'description' => 'Reorder & overstock recommendations derived from sales history.'],
                ['name' => 'Forecast', 'description' => 'Time-series demand forecasts (statsforecast sidecar) for charting.'],
            ],
            'security' => [['bearerAuth' => []]],
            'paths' => self::paths(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum personal access token.'],
                ],
                'responses' => self::responses(),
                'schemas' => self::schemas(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function paths(): array
    {
        return [
            '/api/ping' => [
                'get' => [
                    'tags' => ['Dashboard'],
                    'summary' => 'Health check',
                    'security' => [],
                    'responses' => ['200' => self::json('Pong', ['message' => ['type' => 'string'], 'app' => ['type' => 'string']])],
                ],
            ],

            '/api/auth/register' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Register a new account',
                    'security' => [],
                    'requestBody' => self::body('RegisterInput'),
                    'responses' => [
                        '201' => self::dataResponse('AuthToken', 'Registered'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
            ],
            '/api/auth/login' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Log in and receive a token',
                    'security' => [],
                    'requestBody' => self::body('LoginInput'),
                    'responses' => [
                        '200' => self::dataResponse('AuthToken', 'Logged in'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
            ],
            '/api/auth/logout' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Revoke the current token',
                    'responses' => ['200' => self::messageResponse(), '401' => self::ref('Unauthorized')],
                ],
            ],
            '/api/auth/me' => [
                'get' => [
                    'tags' => ['Auth'],
                    'summary' => 'Get the authenticated user',
                    'responses' => ['200' => self::dataResponse('User'), '401' => self::ref('Unauthorized')],
                ],
            ],

            '/api/dashboard/summary' => [
                'get' => [
                    'tags' => ['Dashboard'],
                    'summary' => 'Inventory KPIs and recent activity',
                    'responses' => ['200' => self::dataResponse('DashboardSummary'), '401' => self::ref('Unauthorized')],
                ],
            ],
            '/api/dashboard/trends' => [
                'get' => [
                    'tags' => ['Dashboard'],
                    'summary' => 'Daily movement totals for charting',
                    'description' => 'Zero-filled per-day in/out unit totals over the trailing window, plus stock value by category. Pass `product_id` to narrow the series to one product (the category breakdown is then empty).',
                    'parameters' => [
                        ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 90, 'default' => 30]],
                        ['name' => 'product_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => self::dataResponse('DashboardTrends'), '401' => self::ref('Unauthorized'), '422' => self::ref('ValidationError')],
                ],
            ],

            '/api/intelligence/recommendations' => [
                'get' => [
                    'tags' => ['Intelligence'],
                    'summary' => 'Reorder & overstock recommendations for every product',
                    'description' => 'Demand comes from the per-product time-series model forecast when a fresh one exists '
                        .'(`forecast_source: model` — see `POST forecast:run` and the Forecast endpoints); otherwise sales velocity '
                        .'falls back to the average over the last 14 days of `out` movements (`forecast_source: fallback`). '
                        .'Lead time is defaulted to 7 days (no per-product lead-time field) and unit cost comes from `product.cost`.',
                    'responses' => ['200' => self::dataResponse('RecommendationsSummary'), '401' => self::ref('Unauthorized')],
                ],
            ],
            '/api/products/{product}/recommendation' => [
                'parameters' => [self::idParam('product')],
                'get' => [
                    'tags' => ['Intelligence'],
                    'summary' => 'Recommendation for a single product',
                    'responses' => ['200' => self::dataResponse('Recommendation'), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
            ],
            '/api/forecast/summary' => [
                'get' => [
                    'tags' => ['Forecast'],
                    'summary' => 'Store-wide demand projection',
                    'description' => 'Every fresh product forecast aggregated per day (expected + p90 demand), with projected 30-day units/revenue and the model mix.',
                    'responses' => ['200' => self::dataResponse('ForecastSummary'), '401' => self::ref('Unauthorized')],
                ],
            ],
            '/api/products/{product}/forecast' => [
                'parameters' => [self::idParam('product')],
                'get' => [
                    'tags' => ['Forecast'],
                    'summary' => 'Daily sales history + model forecast for charting',
                    'description' => 'Last 90 days of zero-filled daily sales plus the stored forecast horizon with p90 bands. '
                        .'`forecast` is empty (and the model fields null) when no fresh forecast exists — run `php artisan forecast:run`.',
                    'responses' => ['200' => self::dataResponse('ProductForecast'), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
            ],

            '/api/categories' => [
                'get' => [
                    'tags' => ['Categories'],
                    'summary' => 'List categories',
                    'responses' => ['200' => self::listResponse('Category'), '401' => self::ref('Unauthorized')],
                ],
                'post' => [
                    'tags' => ['Categories'],
                    'summary' => 'Create a category',
                    'requestBody' => self::body('CategoryInput'),
                    'responses' => [
                        '201' => self::dataResponse('Category', 'Created'),
                        '401' => self::ref('Unauthorized'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
            ],
            '/api/categories/{category}' => [
                'parameters' => [self::idParam('category')],
                'get' => [
                    'tags' => ['Categories'],
                    'summary' => 'Show a category',
                    'responses' => ['200' => self::dataResponse('Category'), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
                'put' => [
                    'tags' => ['Categories'],
                    'summary' => 'Update a category',
                    'requestBody' => self::body('CategoryInput'),
                    'responses' => [
                        '200' => self::dataResponse('Category', 'Updated'),
                        '401' => self::ref('Unauthorized'),
                        '404' => self::ref('NotFound'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
                'delete' => [
                    'tags' => ['Categories'],
                    'summary' => 'Delete a category',
                    'description' => 'Fails with 409 if the category still has products.',
                    'responses' => [
                        '200' => self::messageResponse(),
                        '401' => self::ref('Unauthorized'),
                        '404' => self::ref('NotFound'),
                        '409' => self::ref('Conflict'),
                    ],
                ],
            ],

            '/api/products' => [
                'get' => [
                    'tags' => ['Products'],
                    'summary' => 'List products (paginated & filterable)',
                    'parameters' => self::productFilters(),
                    'responses' => ['200' => self::paginatedResponse('Product'), '401' => self::ref('Unauthorized')],
                ],
                'post' => [
                    'tags' => ['Products'],
                    'summary' => 'Create a product',
                    'description' => 'An optional `quantity` sets the opening stock and records a stock movement.',
                    'requestBody' => self::body('ProductCreateInput'),
                    'responses' => [
                        '201' => self::dataResponse('Product', 'Created'),
                        '401' => self::ref('Unauthorized'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
            ],
            '/api/products/{product}' => [
                'parameters' => [self::idParam('product')],
                'get' => [
                    'tags' => ['Products'],
                    'summary' => 'Show a product',
                    'responses' => ['200' => self::dataResponse('Product'), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
                'put' => [
                    'tags' => ['Products'],
                    'summary' => 'Update product attributes',
                    'description' => 'Stock quantity is NOT updated here — use the stock-adjustments endpoint.',
                    'requestBody' => self::body('ProductInput'),
                    'responses' => [
                        '200' => self::dataResponse('Product', 'Updated'),
                        '401' => self::ref('Unauthorized'),
                        '404' => self::ref('NotFound'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
                'delete' => [
                    'tags' => ['Products'],
                    'summary' => 'Delete a product',
                    'responses' => ['200' => self::messageResponse(), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
            ],

            '/api/stock-movements' => [
                'get' => [
                    'tags' => ['Stock'],
                    'summary' => 'Recent stock movements across all products',
                    'parameters' => [self::queryInt('limit', 'Max rows (1–100).', 20)],
                    'responses' => ['200' => self::listResponse('StockMovement'), '401' => self::ref('Unauthorized')],
                ],
            ],
            '/api/products/{product}/stock-movements' => [
                'parameters' => [self::idParam('product')],
                'get' => [
                    'tags' => ['Stock'],
                    'summary' => 'Movement history for a product',
                    'parameters' => [self::queryInt('per_page', 'Rows per page (1–100).', 15), self::queryInt('page', 'Page number.', 1)],
                    'responses' => ['200' => self::paginatedResponse('StockMovement'), '401' => self::ref('Unauthorized'), '404' => self::ref('NotFound')],
                ],
            ],
            '/api/products/{product}/stock-adjustments' => [
                'parameters' => [self::idParam('product')],
                'post' => [
                    'tags' => ['Stock'],
                    'summary' => 'Adjust stock (in / out / set exact)',
                    'description' => 'An `out`/`adjustment` that would drive quantity below zero is rejected with 422.',
                    'requestBody' => self::body('StockAdjustmentInput'),
                    'responses' => [
                        '201' => self::dataResponse('StockMovement', 'Stock updated'),
                        '401' => self::ref('Unauthorized'),
                        '404' => self::ref('NotFound'),
                        '422' => self::ref('ValidationError'),
                    ],
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array{'$ref': string} */
    private static function ref(string $response): array
    {
        return ['$ref' => "#/components/responses/$response"];
    }

    private static function schemaRef(string $schema): array
    {
        return ['$ref' => "#/components/schemas/$schema"];
    }

    private static function body(string $schema): array
    {
        return [
            'required' => true,
            'content' => ['application/json' => ['schema' => self::schemaRef($schema)]],
        ];
    }

    private static function dataResponse(string $schema, string $description = 'OK'): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['data' => self::schemaRef($schema), 'message' => ['type' => 'string']],
            ]]],
        ];
    }

    private static function listResponse(string $schema): array
    {
        return [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['data' => ['type' => 'array', 'items' => self::schemaRef($schema)]],
            ]]],
        ];
    }

    private static function paginatedResponse(string $schema): array
    {
        return [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => self::schemaRef($schema)],
                    'meta' => self::schemaRef('PaginationMeta'),
                ],
            ]]],
        ];
    }

    private static function messageResponse(): array
    {
        return [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => self::schemaRef('Message')]],
        ];
    }

    /** @param array<string, mixed> $properties */
    private static function json(string $description, array $properties): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => $properties]]],
        ];
    }

    private static function idParam(string $name): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer', 'minimum' => 1],
        ];
    }

    private static function queryInt(string $name, string $description, int $default): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => 'integer', 'default' => $default],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function productFilters(): array
    {
        return [
            ['name' => 'search', 'in' => 'query', 'description' => 'Match name or SKU.', 'schema' => ['type' => 'string']],
            ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'low_stock', 'in' => 'query', 'description' => 'Only products at/below reorder level.', 'schema' => ['type' => 'boolean']],
            ['name' => 'is_active', 'in' => 'query', 'schema' => ['type' => 'boolean']],
            ['name' => 'sort_by', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['name', 'sku', 'price', 'quantity', 'reorder_level', 'created_at', 'updated_at']]],
            ['name' => 'sort_dir', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
            self::queryInt('per_page', 'Rows per page (1–100).', 15),
            self::queryInt('page', 'Page number.', 1),
        ];
    }

    /* ----------------------------------------------- component responses */

    /** @return array<string, mixed> */
    private static function responses(): array
    {
        $msg = ['content' => ['application/json' => ['schema' => self::schemaRef('Message')]]];

        return [
            'Unauthorized' => ['description' => 'Unauthenticated.'] + $msg,
            'NotFound' => ['description' => 'Resource not found.'] + $msg,
            'Conflict' => ['description' => 'Business-rule conflict.'] + $msg,
            'ValidationError' => [
                'description' => 'Validation failed.',
                'content' => ['application/json' => ['schema' => self::schemaRef('ValidationError')]],
            ],
        ];
    }

    /* ------------------------------------------------- component schemas */

    /** @return array<string, mixed> */
    private static function schemas(): array
    {
        $dt = ['type' => 'string', 'format' => 'date-time', 'nullable' => true];

        return [
            'Message' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'current_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer', 'nullable' => true],
                    'to' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'created_at' => $dt,
                ],
            ],
            'AuthToken' => [
                'type' => 'object',
                'properties' => [
                    'token' => ['type' => 'string'],
                    'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                    'user' => self::schemaRef('User'),
                ],
            ],
            'Category' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'products_count' => ['type' => 'integer', 'nullable' => true],
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ],
            ],
            'Product' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'category_id' => ['type' => 'integer'],
                    'sku' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'price' => ['type' => 'number', 'format' => 'float'],
                    'cost' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    'quantity' => ['type' => 'integer'],
                    'reorder_level' => ['type' => 'integer'],
                    'is_active' => ['type' => 'boolean'],
                    'is_low_stock' => ['type' => 'boolean'],
                    'stock_value' => ['type' => 'number', 'format' => 'float'],
                    'category' => array_merge(self::schemaRef('Category'), ['nullable' => true]),
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ],
            ],
            'StockMovement' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'product_id' => ['type' => 'integer'],
                    'product_name' => ['type' => 'string', 'nullable' => true],
                    'type' => ['type' => 'string', 'enum' => ['in', 'out', 'adjustment']],
                    'type_label' => ['type' => 'string'],
                    'quantity' => ['type' => 'integer'],
                    'quantity_before' => ['type' => 'integer'],
                    'quantity_after' => ['type' => 'integer'],
                    'change' => ['type' => 'integer'],
                    'reason' => ['type' => 'string', 'nullable' => true],
                    'user_id' => ['type' => 'integer', 'nullable' => true],
                    'user_name' => ['type' => 'string', 'nullable' => true],
                    'created_at' => $dt,
                ],
            ],
            'DashboardSummary' => [
                'type' => 'object',
                'properties' => [
                    'total_products' => ['type' => 'integer'],
                    'active_products' => ['type' => 'integer'],
                    'total_categories' => ['type' => 'integer'],
                    'low_stock_count' => ['type' => 'integer'],
                    'out_of_stock_count' => ['type' => 'integer'],
                    'total_stock_units' => ['type' => 'integer'],
                    'total_stock_value' => ['type' => 'number', 'format' => 'float'],
                    'low_stock_products' => ['type' => 'array', 'items' => self::schemaRef('Product')],
                    'recent_movements' => ['type' => 'array', 'items' => self::schemaRef('StockMovement')],
                ],
            ],

            'DashboardTrends' => [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer'],
                    'series' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'units_in' => ['type' => 'integer'],
                        'units_out' => ['type' => 'integer'],
                        'movements' => ['type' => 'integer'],
                    ]]],
                    'category_values' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'category_id' => ['type' => 'integer'],
                        'category_name' => ['type' => 'string'],
                        'stock_value' => ['type' => 'number', 'format' => 'float'],
                        'units' => ['type' => 'integer'],
                    ]]],
                ],
            ],

            'Recommendation' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'sku' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'category_name' => ['type' => 'string', 'nullable' => true],
                    'is_active' => ['type' => 'boolean'],
                    'type' => ['type' => 'string', 'enum' => ['reorder', 'overstock', 'healthy']],
                    'current_stock' => ['type' => 'integer'],
                    'sales_velocity' => ['type' => 'number', 'format' => 'float', 'description' => 'Average units sold per day over the velocity window.'],
                    'days_of_stock_left' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'description' => 'Null when there are no recent sales.'],
                    'lead_time_days' => ['type' => 'integer'],
                    'lead_time_is_default' => ['type' => 'boolean'],
                    'unit_cost' => ['type' => 'number', 'format' => 'float'],
                    'unit_cost_is_default' => ['type' => 'boolean'],
                    'needs_reorder' => ['type' => 'boolean'],
                    'suggested_reorder_qty' => ['type' => 'integer'],
                    'reorder_by_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'is_urgent' => ['type' => 'boolean'],
                    'is_overstocked' => ['type' => 'boolean'],
                    'cash_tied_up' => ['type' => 'number', 'format' => 'float'],
                    'reasoning' => ['type' => 'string'],
                    'forecast_source' => ['type' => 'string', 'enum' => ['model', 'fallback'], 'description' => 'Whether the demand estimate came from the time-series model or the window-average fallback.'],
                    'model_used' => ['type' => 'string', 'nullable' => true, 'description' => 'Forecasting model (AutoETS, MSTL, CrostonOptimized, TSB, SeasonalNaive, Naive); null on fallback.'],
                    'forecast_generated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'projected_stockout_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'description' => 'First day cumulative forecast demand exhausts on-hand stock; null when stock outlasts the horizon (or on fallback).'],
                    'stockout_risk' => ['type' => 'string', 'enum' => ['high', 'medium', 'low'], 'nullable' => true, 'description' => 'high: expected lead-time demand exhausts stock; medium: the p90 worst case does; low: covered.'],
                    'demand_trend_pct' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'description' => 'Expected next-28-day demand vs the previous 28 days of actuals, in percent.'],
                    'projected_units_30d' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    'projected_revenue_30d' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                ],
            ],
            'RecommendationsSummary' => [
                'type' => 'object',
                'properties' => [
                    'reorder_count' => ['type' => 'integer'],
                    'overstock_count' => ['type' => 'integer'],
                    'healthy_count' => ['type' => 'integer'],
                    'dead_stock_count' => ['type' => 'integer', 'description' => 'Products the model expects no further demand for while stock remains.'],
                    'dead_stock_cash_recoverable' => ['type' => 'number', 'format' => 'float'],
                    'forecasted_count' => ['type' => 'integer', 'description' => 'Products whose recommendation used a fresh model forecast.'],
                    'projected_revenue_30d' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'description' => 'Store-wide expected revenue over the next 30 days; null when nothing is forecasted.'],
                    'total_cash_tied_up' => ['type' => 'number', 'format' => 'float'],
                    'velocity_window_days' => ['type' => 'integer'],
                    'default_lead_time_days' => ['type' => 'integer'],
                    'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'recommendations' => ['type' => 'array', 'items' => self::schemaRef('Recommendation')],
                ],
            ],
            'ForecastPoint' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'format' => 'date'],
                    'mean' => ['type' => 'number', 'format' => 'float'],
                    'lo_90' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'description' => 'Null for models without prediction intervals (Croston/TSB).'],
                    'hi_90' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                ],
            ],
            'ForecastSummary' => [
                'type' => 'object',
                'properties' => [
                    'forecasted_count' => ['type' => 'integer'],
                    'projected_units_30d' => ['type' => 'number', 'format' => 'float'],
                    'projected_revenue_30d' => ['type' => 'number', 'format' => 'float'],
                    'model_mix' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer'], 'description' => 'Model name → product count.'],
                    'generated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'daily' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'mean' => ['type' => 'number', 'format' => 'float'],
                        'hi_90' => ['type' => 'number', 'format' => 'float'],
                    ]]],
                ],
            ],
            'ProductForecast' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'sku' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'generated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'model_used' => ['type' => 'string', 'nullable' => true],
                    'horizon_days' => ['type' => 'integer', 'nullable' => true],
                    'history' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'qty' => ['type' => 'integer'],
                    ]]],
                    'forecast' => ['type' => 'array', 'items' => self::schemaRef('ForecastPoint')],
                ],
            ],

            // Request bodies
            'RegisterInput' => [
                'type' => 'object',
                'required' => ['name', 'email', 'password', 'password_confirmation'],
                'properties' => [
                    'name' => ['type' => 'string', 'example' => 'Jane Doe'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'jane@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'example' => 'password123'],
                    'password_confirmation' => ['type' => 'string', 'format' => 'password', 'example' => 'password123'],
                ],
            ],
            'LoginInput' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'demo@retail.test'],
                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password'],
                ],
            ],
            'CategoryInput' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'example' => 'Beverages'],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'ProductInput' => [
                'type' => 'object',
                'required' => ['category_id', 'sku', 'name', 'price'],
                'properties' => [
                    'category_id' => ['type' => 'integer', 'example' => 1],
                    'sku' => ['type' => 'string', 'example' => 'BEV-001'],
                    'name' => ['type' => 'string', 'example' => 'Cola 330ml'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'price' => ['type' => 'number', 'format' => 'float', 'example' => 1.2],
                    'cost' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'example' => 0.55],
                    'reorder_level' => ['type' => 'integer', 'default' => 0, 'example' => 50],
                    'is_active' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            'ProductCreateInput' => [
                'allOf' => [
                    self::schemaRef('ProductInput'),
                    [
                        'type' => 'object',
                        'properties' => [
                            'quantity' => ['type' => 'integer', 'default' => 0, 'description' => 'Opening stock.', 'example' => 100],
                        ],
                    ],
                ],
            ],
            'StockAdjustmentInput' => [
                'type' => 'object',
                'required' => ['type', 'quantity'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['in', 'out', 'adjustment'], 'example' => 'in'],
                    'quantity' => ['type' => 'integer', 'minimum' => 0, 'example' => 25],
                    'reason' => ['type' => 'string', 'nullable' => true, 'example' => 'Restock'],
                ],
            ],
        ];
    }
}
