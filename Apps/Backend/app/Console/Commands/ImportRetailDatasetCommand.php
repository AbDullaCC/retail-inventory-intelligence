<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Auth\Models\User;
use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Shopify\Models\ShopifyProductMap;
use App\Modules\Shopify\Models\ShopifySyncState;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\RetailDataset\RetailDatasetImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the database with a curated, date-shifted slice of the Online Retail II
 * dataset so recommendations and forecasts run against real retail sales.
 *
 * Data: Chen, Daqing (2019). Online Retail II. UCI Machine Learning Repository.
 * https://doi.org/10.24432/C5CG6D — CC BY 4.0. Sales history is real; costs,
 * categories and replenishment movements are synthesized (see README).
 */
final class ImportRetailDatasetCommand extends Command
{
    protected $signature = 'inventory:import-retail
        {--fresh : Truncate categories, products and stock movements before importing}
        {--file= : Path to online_retail_II.xlsx (defaults to storage/app/private/datasets)}
        {--skus=250 : Number of SKUs to curate into the demo catalogue}';

    protected $description = 'Import real retail sales history (UCI Online Retail II) as the demo catalogue.';

    private const DATASET_URL = 'https://archive.ics.uci.edu/static/public/502/online+retail+ii.zip';

    public function handle(RetailDatasetImporter $importer): int
    {
        ini_set('memory_limit', '1024M');

        $path = (string) ($this->option('file') ?: storage_path('app/private/datasets/online_retail_II.xlsx'));
        if (! is_file($path)) {
            $this->error(sprintf('Dataset not found at %s', $path));
            $this->line('Download it (43.5 MB, CC BY 4.0):');
            $this->line('  1. '.self::DATASET_URL);
            $this->line('  2. Unzip and place online_retail_II.xlsx at the path above (or pass --file=).');

            return self::FAILURE;
        }

        if (! $this->prepareDatabase()) {
            return self::FAILURE;
        }

        $started = microtime(true);

        $this->components->info('Phase 1/3 — streaming workbook (~1.07M rows, both year sheets)…');
        $bar = $this->output->createProgressBar();
        $parsed = $importer->aggregate($path, fn (int $rows) => $bar->setProgress($rows));
        $bar->finish();
        $this->newLine(2);
        $this->components->twoColumnDetail('Rows read / skipped / duplicates', sprintf(
            '%s / %s / %s',
            number_format($parsed['rows_read']),
            number_format($parsed['rows_skipped']),
            number_format($parsed['duplicates_dropped']),
        ));

        $this->components->info('Phase 2/3 — curating catalogue…');
        $selected = $importer->curate($parsed['aggregates'], $parsed['max_day'], max(10, (int) $this->option('skus')));
        $this->components->twoColumnDetail('SKUs selected', (string) count($selected));

        $this->components->info('Phase 3/3 — simulating replenishment & inserting ledger…');
        $bar = $this->output->createProgressBar(count($selected));
        $summary = $importer->import($parsed['aggregates'], $selected, $parsed['max_day'], fn (int $done) => $bar->setProgress($done));
        $bar->finish();
        $this->newLine(2);

        $this->components->twoColumnDetail('Products / categories', sprintf('%d / %d', $summary['products'], $summary['categories']));
        $this->components->twoColumnDetail('Movements (in / out)', sprintf(
            '%s (%s / %s)',
            number_format($summary['movements_in'] + $summary['movements_out']),
            number_format($summary['movements_in']),
            number_format($summary['movements_out']),
        ));
        $this->components->twoColumnDetail('Units sold', number_format($summary['units_sold']));
        $this->components->twoColumnDetail('Ledger range', sprintf('%s → %s', $summary['first_day'], $summary['last_day']));
        $this->components->twoColumnDetail('Elapsed', sprintf('%.1fs', microtime(true) - $started));

        $this->newLine();
        $this->line('<fg=gray>Data: Chen, Daqing (2019). Online Retail II. UCI ML Repository (doi:10.24432/C5CG6D, CC BY 4.0).</>');
        $this->line('<fg=gray>Sales are real; costs, categories and replenishment are synthesized. Timeline shifted to end yesterday.</>');
        $this->newLine();
        $this->components->info('Done. Try: php artisan inventory:insights');

        return self::SUCCESS;
    }

    private function prepareDatabase(): bool
    {
        $hasData = Product::query()->exists() || StockMovement::query()->exists();

        if ($hasData && ! $this->option('fresh')) {
            $this->error('The catalogue is not empty. Re-run with --fresh to truncate categories, products and stock movements first.');

            return false;
        }

        if ($this->option('fresh')) {
            Schema::disableForeignKeyConstraints();
            StockMovement::query()->truncate();
            Product::query()->truncate();
            Category::query()->truncate();
            // Truncation skips FK cascades, so the Shopify connector state
            // must be reset too: stale variant→product maps would point at
            // dead ids, and a null watermark makes the next sync re-backfill
            // the store's order history. Credentials are kept.
            ShopifyProductMap::query()->truncate();
            ShopifySyncState::query()->truncate();
            Schema::enableForeignKeyConstraints();
            $this->components->warn('Truncated stock_movements, products, categories and Shopify sync state.');
        }

        User::query()->firstOrCreate(
            ['email' => 'demo@retail.test'],
            ['name' => 'Demo Manager', 'password' => 'password'],
        );

        return true;
    }
}
