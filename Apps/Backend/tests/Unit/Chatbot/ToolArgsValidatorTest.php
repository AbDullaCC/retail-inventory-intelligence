<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Services\Tools\ToolArgsValidator;
use PHPUnit\Framework\TestCase;

class ToolArgsValidatorTest extends TestCase
{
    public function test_accepts_args_matching_the_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'verdict' => ['type' => 'string', 'enum' => ['reorder', 'overstock']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'required' => ['product_id'],
        ];

        $this->assertTrue(ToolArgsValidator::valid(['product_id' => 5, 'verdict' => 'reorder', 'limit' => 20], $schema));
    }

    public function test_rejects_missing_required_argument(): void
    {
        $schema = ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer']], 'required' => ['product_id']];

        $this->assertFalse(ToolArgsValidator::valid([], $schema));
    }

    public function test_rejects_wrong_type_for_a_property(): void
    {
        $schema = ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer']], 'required' => ['product_id']];

        $this->assertFalse(ToolArgsValidator::valid(['product_id' => 'abc'], $schema));
    }

    public function test_rejects_value_outside_minimum_or_maximum(): void
    {
        $schema = ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]], 'required' => ['limit']];

        $this->assertFalse(ToolArgsValidator::valid(['limit' => 0], $schema));
        $this->assertFalse(ToolArgsValidator::valid(['limit' => 51], $schema));
    }

    public function test_rejects_value_not_in_enum(): void
    {
        $schema = ['type' => 'object', 'properties' => ['verdict' => ['type' => 'string', 'enum' => ['reorder', 'overstock']]], 'required' => ['verdict']];

        $this->assertFalse(ToolArgsValidator::valid(['verdict' => 'bogus'], $schema));
    }

    public function test_tolerates_unknown_properties(): void
    {
        $schema = ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer']], 'required' => ['product_id']];

        $this->assertTrue(ToolArgsValidator::valid(['product_id' => 1, 'surprise' => 'x'], $schema));
    }

    public function test_accepts_numeric_string_integers_from_the_model(): void
    {
        $schema = ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer']], 'required' => ['product_id']];

        $this->assertTrue(ToolArgsValidator::valid(['product_id' => '7'], $schema));
    }
}
