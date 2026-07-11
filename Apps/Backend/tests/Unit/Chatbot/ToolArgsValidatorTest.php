<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Services\Tools\ToolArgsValidator;
use PHPUnit\Framework\TestCase;

final class ToolArgsValidatorTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'product_id' => ['type' => 'integer', 'minimum' => 1],
            'verdict' => ['type' => 'string', 'enum' => ['reorder', 'healthy']],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
        ],
        'required' => ['product_id'],
    ];

    public function test_accepts_matching_args(): void
    {
        $this->assertTrue(ToolArgsValidator::valid(
            ['product_id' => 7, 'verdict' => 'reorder', 'limit' => 20],
            self::SCHEMA,
        ));
    }

    public function test_rejects_a_missing_required_argument(): void
    {
        $this->assertFalse(ToolArgsValidator::valid(['verdict' => 'reorder'], self::SCHEMA));
    }

    public function test_rejects_a_wrong_type(): void
    {
        $this->assertFalse(ToolArgsValidator::valid(['product_id' => 'seven'], self::SCHEMA));
    }

    public function test_accepts_numeric_string_integers_from_the_model(): void
    {
        $this->assertTrue(ToolArgsValidator::valid(['product_id' => '7'], self::SCHEMA));
    }

    public function test_rejects_out_of_range_values(): void
    {
        $this->assertFalse(ToolArgsValidator::valid(['product_id' => 0], self::SCHEMA));
        $this->assertFalse(ToolArgsValidator::valid(['product_id' => 1, 'limit' => 51], self::SCHEMA));
    }

    public function test_rejects_unknown_enum_values(): void
    {
        $this->assertFalse(ToolArgsValidator::valid(['product_id' => 1, 'verdict' => 'panic'], self::SCHEMA));
    }

    public function test_tolerates_unknown_properties(): void
    {
        $this->assertTrue(ToolArgsValidator::valid(['product_id' => 1, 'extra' => 'ignored'], self::SCHEMA));
    }

    public function test_empty_schema_accepts_anything(): void
    {
        $this->assertTrue(ToolArgsValidator::valid(['whatever' => true], []));
    }
}
