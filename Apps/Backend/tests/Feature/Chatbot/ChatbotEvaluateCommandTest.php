<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The golden-question harness against a faked Gemini: right answers pass,
 * wrong answers fail the run. (The real value of the command is live runs
 * against the real model — these tests pin the scoring machinery.)
 */
class ChatbotEvaluateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.chatbot', [
            'gemini' => [
                'api_key' => 'test-key',
                'model' => 'gemini-test',
                'base_url' => 'https://gemini.fake/v1beta',
                'timeout' => 5,
            ],
            'max_tokens' => 512,
            'temperature' => 0.2,
            'max_history_messages' => 12,
            'max_tool_iterations' => 5,
            'max_tool_result_items' => 20,
            'rate_limit_per_hour' => 30,
        ]);

        $category = Category::factory()->create();
        // Inventory worth exactly 30 × $4.00 = $120.00.
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Golden Widget',
            'price' => 4.00,
            'quantity' => 30,
        ]);
    }

    private function fakeAnswer(string $text): void
    {
        Http::fake(['*generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $text]], 'role' => 'model']]],
        ])]);
    }

    public function test_list_mode_prints_the_golden_set_without_calling_the_llm(): void
    {
        Http::fake();

        $this->artisan('chatbot:evaluate', ['--list' => true])
            ->expectsOutputToContain('inventory-value')
            ->expectsOutputToContain('read-only-refusal')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_correct_answer_passes(): void
    {
        $this->fakeAnswer('Your inventory is currently worth $120.00 in total.');

        $this->artisan('chatbot:evaluate', ['--only' => ['inventory-value']])
            ->expectsOutputToContain('PASS')
            ->assertSuccessful();
    }

    public function test_a_wrong_answer_fails_the_run(): void
    {
        $this->fakeAnswer('Your inventory is currently worth $999,999.00 in total.');

        $this->artisan('chatbot:evaluate', ['--only' => ['inventory-value']])
            ->expectsOutputToContain('FAIL')
            ->assertFailed();
    }

    public function test_unknown_only_key_errors(): void
    {
        Http::fake();

        $this->artisan('chatbot:evaluate', ['--only' => ['nope']])->assertFailed();
    }
}
