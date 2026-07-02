<?php

declare(strict_types=1);

namespace Database\Seeders\RetailDataset;

/**
 * Maps a product description from the Online Retail II dataset onto a small,
 * demo-friendly set of retail categories. The dataset itself has no category
 * data, so this is a pragmatic keyword classification: the map is ORDERED and
 * the first matching category wins — specific categories (e.g. Candles for
 * "T-LIGHT") must be listed before broader ones that share keywords (Lighting,
 * Home Décor).
 */
final class CategoryKeywordMap
{
    public const FALLBACK = 'General Merchandise';

    /**
     * Category => keywords matched anywhere in the uppercase description.
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        'Christmas & Seasonal' => ['CHRISTMAS', 'XMAS', 'ADVENT', 'SANTA', 'REINDEER', 'SNOWFLAKE', 'EASTER', 'HALLOWEEN', 'VALENTINE'],
        'Candles & Fragrance' => ['CANDLE', 'T-LIGHT', 'TEALIGHT', 'INCENSE', 'SCENTED'],
        'Lighting' => ['LIGHT', 'LANTERN', 'LAMP', 'NIGHTLIGHT'],
        'Kitchen & Dining' => ['MUG', 'CUP', 'TEAPOT', 'BOWL', 'PLATE', 'JUG', 'CAKE', 'BAKING', 'LUNCH BOX', 'RECIPE', 'TRAY', 'COASTER', 'NAPKIN', 'TEA TOWEL', 'EGG', 'CUTLERY'],
        'Storage & Organisation' => ['BOX', 'CRATE', 'BASKET', 'TIN', 'JAR', 'DRAWER', 'HOOK', 'PEG', 'STORAGE'],
        'Stationery & Craft' => ['CARD', 'WRAP', 'RIBBON', 'TAPE', 'PENCIL', 'PEN', 'NOTEBOOK', 'JOURNAL', 'STICKER', 'CHALK', 'CRAFT', 'PAPER', 'ENVELOPE', 'GIFT TAG'],
        'Bags & Accessories' => ['BAG', 'PURSE', 'WALLET', 'UMBRELLA', 'SCARF', 'NECKLACE', 'BRACELET', 'EARRING', 'JEWELLERY', 'PASSPORT'],
        'Toys & Games' => ['TOY', 'GAME', 'PUZZLE', 'DOLL', 'SOLDIER', 'SPACEBOY', 'PLAYHOUSE', 'SKIPPING', 'JIGSAW', 'DOMINO'],
        'Garden & Outdoor' => ['GARDEN', 'PLANT', 'WATERING', 'BIRD', 'PARASOL', 'PICNIC', 'WINDMILL', 'THERMOMETER'],
        'Home Décor' => ['HEART', 'SIGN', 'FRAME', 'MIRROR', 'CUSHION', 'BUNTING', 'DECORATION', 'ORNAMENT', 'WICKER', 'DOORMAT', 'CLOCK', 'HANGING'],
    ];

    public function categorize(string $description): string
    {
        $upper = mb_strtoupper($description);

        foreach (self::MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($upper, $keyword)) {
                    return $category;
                }
            }
        }

        return self::FALLBACK;
    }

    /**
     * All categories the map can produce, fallback included — used to create
     * the Category rows up front.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return [...array_keys(self::MAP), self::FALLBACK];
    }
}
