<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Modules\Chatbot\Services\Tools\ChatbotTool;
use App\Modules\Chatbot\Services\Tools\ChatbotToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    public function test_builds_a_name_keyed_map_and_supports_lookup(): void
    {
        $alpha = $this->tool('alpha');
        $beta = $this->tool('beta');

        $registry = new ChatbotToolRegistry([$alpha, $beta]);

        $this->assertSame(['alpha' => $alpha, 'beta' => $beta], $registry->all());
        $this->assertTrue($registry->has('alpha'));
        $this->assertFalse($registry->has('nope'));
        $this->assertSame($beta, $registry->get('beta'));
    }

    public function test_a_later_tool_with_the_same_name_overrides_the_earlier(): void
    {
        $first = $this->tool('dupe', 'first');
        $second = $this->tool('dupe', 'second');

        $registry = new ChatbotToolRegistry([$first, $second]);

        $this->assertCount(1, $registry->all());
        $this->assertSame('second', $registry->get('dupe')->description);
    }

    private function tool(string $name, string $description = ''): ChatbotTool
    {
        return new ChatbotTool($name, $description, ['type' => 'object', 'properties' => new \stdClass], static fn (): array => []);
    }
}
