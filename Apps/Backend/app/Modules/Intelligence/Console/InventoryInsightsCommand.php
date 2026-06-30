<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Console;

use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Prints the per-product reorder/overstock table against the live data, so the
 * intelligence layer can be sanity-checked from the CLI.
 */
final class InventoryInsightsCommand extends Command
{
    protected $signature = 'inventory:insights';

    protected $description = 'Print per-product reorder/overstock recommendations derived from movement history.';

    public function handle(IntelligenceServiceInterface $service): int
    {
        $summary = $service->recommendations();

        $rows = array_map(static function ($r): array {
            return [
                $r->sku,
                Str::limit($r->name, 22),
                number_format($r->salesVelocity, 2),
                $r->daysOfStockLeft === null ? '—' : (string) round($r->daysOfStockLeft),
                $r->needsReorder ? 'YES'.($r->isUrgent ? ' (urgent)' : '') : '',
                $r->needsReorder ? (string) $r->suggestedReorderQty : '',
                $r->isOverstocked ? 'YES' : '',
                '$'.number_format($r->cashTiedUp, 2),
                $r->type,
            ];
        }, $summary->recommendations);

        $this->table(
            ['SKU', 'Product', 'Vel/day', 'Days left', 'Reorder?', 'Qty', 'Overstock?', 'Cash tied up', 'Verdict'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf(
            'Reorder: %d   Overstock: %d   Healthy: %d   Total cash tied up: $%s',
            $summary->reorderCount,
            $summary->overstockCount,
            $summary->healthyCount,
            number_format($summary->totalCashTiedUp, 2),
        ));
        $this->line(sprintf(
            '<fg=gray>velocity window = %dd · lead time defaulted to %dd for all products (no lead-time field) · unit cost from product.cost</>',
            $summary->velocityWindowDays,
            $summary->defaultLeadTimeDays,
        ));

        return self::SUCCESS;
    }
}
