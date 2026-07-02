<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Streams the Online Retail II workbook (both year sheets) row by row and
 * yields only clean sale/return lines. Owns every data-quality rule for the
 * raw file: non-product stock codes, zero/invalid prices and quantities, and
 * exact duplicate lines (which also removes the invoices repeated across the
 * two overlapping year sheets).
 *
 * Yields tuples: [string $sku, string $day (Y-m-d), int $quantity (signed),
 * float $price, string $description]. Negative quantities are cancellations
 * ("C" invoices) and are netted downstream.
 */
final class XlsxSalesReader
{
    /** Service/fee rows that the stock-code regex alone would not exclude. */
    private const BLACKLIST = [
        'POSTAGE', 'DOT', 'M', 'BANK CHARGES', 'ADJUST', 'ADJUST2', 'AMAZONFEE',
        'B', 'CRUK', 'S', 'C2', 'TEST001', 'TEST002', 'PADS', 'DCGSSBOY', 'DCGSSGIRL',
    ];

    public int $rowsRead = 0;

    public int $rowsSkipped = 0;

    public int $duplicatesDropped = 0;

    /**
     * @return Generator<array{0: string, 1: string, 2: int, 3: float, 4: string}>
     */
    public function rows(string $path): Generator
    {
        /** @var array<string, true> $seen */
        $seen = [];

        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $isHeader = true;

                foreach ($sheet->getRowIterator() as $row) {
                    if ($isHeader) {
                        $isHeader = false;

                        continue;
                    }

                    $this->rowsRead++;

                    $cells = $row->toArray();
                    // Columns: Invoice, StockCode, Description, Quantity, InvoiceDate, Price, Customer ID, Country
                    if (count($cells) < 6) {
                        $this->rowsSkipped++;

                        continue;
                    }

                    $sku = mb_strtoupper(trim((string) $cells[1]));
                    $description = trim((string) ($cells[2] ?? ''));
                    $quantity = (int) $cells[3];
                    $price = (float) $cells[5];

                    if ($quantity === 0 || $price <= 0.0 || ! $this->isProductCode($sku)) {
                        $this->rowsSkipped++;

                        continue;
                    }

                    $day = $this->day($cells[4]);
                    if ($day === null) {
                        $this->rowsSkipped++;

                        continue;
                    }

                    // Raw md5 keeps the ~1M-entry dedupe set memory-bounded.
                    $hash = md5(sprintf('%s|%s|%d|%s|%s', (string) $cells[0], $sku, $quantity, $day, $cells[5]), true);
                    if (isset($seen[$hash])) {
                        $this->duplicatesDropped++;

                        continue;
                    }
                    $seen[$hash] = true;

                    yield [$sku, $day, $quantity, $price, $description];
                }
            }
        } finally {
            $reader->close();
        }
    }

    private function isProductCode(string $sku): bool
    {
        if ($sku === '' || in_array($sku, self::BLACKLIST, true) || str_starts_with($sku, 'GIFT_')) {
            return false;
        }

        return preg_match('/^\d{5}[A-Z]{0,2}$/', $sku) === 1;
    }

    private function day(mixed $cell): ?string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_string($cell) && $cell !== '') {
            $timestamp = strtotime($cell);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return null;
    }
}
