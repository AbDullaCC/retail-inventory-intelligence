<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Support;

/**
 * Fuzzy-but-strict matching between an LLM answer (prose with formatted
 * numbers like "$943,033.23" or "13,094 units") and expected facts computed
 * from the database. Used by `chatbot:evaluate`.
 */
final class AnswerChecker
{
    /**
     * Every numeric token in the answer, normalised ("1,234.5" → 1234.5).
     *
     * @return list<float>
     */
    public static function numbers(string $answer): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $answer, $matches);

        return array_map(
            static fn (string $token): float => (float) str_replace(',', '', $token),
            $matches[0],
        );
    }

    /**
     * Does the answer state a number close enough to $expected? A match is
     * within $absTolerance OR within $tolerancePct percent — the absolute
     * bound serves small numbers (counts), the relative bound large ones.
     */
    public static function hasNumber(
        string $answer,
        float $expected,
        float $tolerancePct = 1.0,
        float $absTolerance = 0.5,
    ): bool {
        foreach (self::numbers($answer) as $number) {
            $diff = abs($number - $expected);

            if ($diff <= $absTolerance) {
                return true;
            }

            if ($expected !== 0.0 && ($diff / abs($expected)) * 100 <= $tolerancePct) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@see hasNumber} for whole-number counts — additionally accepts a zero
     * stated in words ("none", "no products"), which models prefer over a
     * literal 0 in prose.
     */
    public static function hasCount(string $answer, int $expected): bool
    {
        if (self::hasNumber($answer, (float) $expected, 0.0, 0.4)) {
            return true;
        }

        return $expected === 0 && self::hasAnyText($answer, [
            'none', 'no products', 'no items', 'zero', 'not any', "aren't any", 'nothing is',
        ]);
    }

    public static function hasText(string $answer, string $needle): bool
    {
        return str_contains(mb_strtolower($answer), mb_strtolower($needle));
    }

    /**
     * @param  list<string>  $needles
     */
    public static function hasAnyText(string $answer, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (self::hasText($answer, $needle)) {
                return true;
            }
        }

        return false;
    }
}
