<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Support\AnswerChecker;
use PHPUnit\Framework\TestCase;

final class AnswerCheckerTest extends TestCase
{
    public function test_extracts_formatted_numbers(): void
    {
        $numbers = AnswerChecker::numbers('Your inventory is worth $943,033.23 across 13,094 units (8.3% more).');

        $this->assertContains(943033.23, $numbers);
        $this->assertContains(13094.0, $numbers);
        $this->assertContains(8.3, $numbers);
    }

    public function test_matches_a_number_within_absolute_tolerance(): void
    {
        // "approximately 29 days" against an expected 29.5
        $this->assertTrue(AnswerChecker::hasNumber('lasts about 29 days', 29.5, 0.0, 0.5));
        $this->assertFalse(AnswerChecker::hasNumber('lasts about 25 days', 29.5, 0.0, 0.5));
    }

    public function test_matches_a_number_within_relative_tolerance(): void
    {
        // "about 1,997 units" against an expected 1997.2 (0.01%)
        $this->assertTrue(AnswerChecker::hasNumber('about 1,997 units', 1997.2, 1.0, 0.0));
        // 2,100 vs 1,997.2 is ~5% off — outside 3%
        $this->assertFalse(AnswerChecker::hasNumber('about 2,100 units', 1997.2, 3.0, 0.0));
    }

    public function test_exact_integer_matching_via_small_absolute_tolerance(): void
    {
        $this->assertTrue(AnswerChecker::hasNumber('59 products need reordering', 59.0, 0.0, 0.4));
        $this->assertFalse(AnswerChecker::hasNumber('60 products need reordering', 59.0, 0.0, 0.4));
    }

    public function test_text_matching_is_case_insensitive(): void
    {
        $answer = 'The most urgent item is the **Alarm Clock Bakelike Red**.';

        $this->assertTrue(AnswerChecker::hasText($answer, 'alarm clock bakelike red'));
        $this->assertFalse(AnswerChecker::hasText($answer, 'popcorn holder'));
        $this->assertTrue(AnswerChecker::hasAnyText($answer, ['popcorn', 'alarm clock']));
    }
}
