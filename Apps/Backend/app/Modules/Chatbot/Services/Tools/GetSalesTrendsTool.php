<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Services\Tools;

use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;

/**
 * `get_sales_trends` — daily units in/out over a trailing window (store-wide
 * or for one product) plus per-category breakdowns: units SOLD by category
 * over the window and current stock VALUE by category. The daily series is
 * summarised into totals; day-level rows are only included for short windows.
 */
final class GetSalesTrendsTool
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    public function build(): Tool
    {
        return new Tool(
            name: 'get_sales_trends',
            description: 'Sales/receiving activity over the last N days (default 30, max 90): total units sold and received, busiest day, units SOLD per category over the window (units_sold_by_category — server-computed ranking), and current stock value per category. Pass product_id to narrow to one product. Day-by-day rows included when days <= 31. Use for "how were sales this month?", "which category sold the most?", "which category holds the most value?".',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 90],
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            handler: function (array $args): array {
                $days = max(7, min(90, (int) ($args['days'] ?? 30)));
                $productId = isset($args['product_id']) ? (int) $args['product_id'] : null;

                $trends = $this->dashboard->trends($days, $productId);

                $series = $trends->toArray()['series'];
                $unitsOut = array_sum(array_column($series, 'units_out'));
                $unitsIn = array_sum(array_column($series, 'units_in'));

                $busiest = null;
                foreach ($series as $day) {
                    if ($busiest === null || $day['units_out'] > $busiest['units_out']) {
                        $busiest = $day;
                    }
                }

                $result = [
                    'days' => $days,
                    'product_id' => $productId,
                    'total_units_sold' => (int) $unitsOut,
                    'total_units_received' => (int) $unitsIn,
                    'busiest_day' => $busiest,
                ];

                if ($days <= 31) {
                    $result['daily'] = $series;
                }

                if ($productId === null) {
                    // Ranked server-side — the model must never sum or rank rows itself.
                    $result['units_sold_by_category'] = $this->dashboard->salesByCategory($days);
                    $result['stock_value_by_category'] = $trends->toArray()['category_values'];
                }

                return $result;
            },
        );
    }
}
