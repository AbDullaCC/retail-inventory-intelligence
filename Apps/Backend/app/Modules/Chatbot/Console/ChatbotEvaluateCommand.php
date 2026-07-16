<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Console;

use App\Modules\Chatbot\Exceptions\ChatUnavailableException;
use App\Modules\Chatbot\Services\ChatOrchestrator;
use App\Modules\Chatbot\Support\AnswerChecker;
use App\Modules\Dashboard\Services\Contracts\DashboardServiceInterface;
use App\Modules\Forecast\Services\Contracts\ForecastReaderInterface;
use App\Modules\Intelligence\DTOs\RecommendationsSummaryDTO;
use App\Modules\Intelligence\Services\Contracts\IntelligenceServiceInterface;
use App\Modules\Product\Models\Product;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Golden-question evaluation of the AI assistant — the chatbot's equivalent
 * of `forecast:evaluate`. Each golden question is asked through the REAL
 * orchestrator (real tools, real Gemini), and the answer is scored against
 * ground truth computed at runtime from the same services the tools wrap.
 *
 * Run it before every demo. Requires GEMINI_API_KEY; a full run makes roughly
 * 2× as many Gemini requests as there are questions (tool loops), which
 * counts against the free tier's daily quota.
 */
final class ChatbotEvaluateCommand extends Command
{
    protected $signature = 'chatbot:evaluate
        {--only=* : Run only these question keys}
        {--list : List the golden questions without calling the LLM}';

    protected $description = 'Score the AI assistant against golden questions with database-computed expected answers.';

    private ?RecommendationsSummaryDTO $summary = null;

    public function handle(ChatOrchestrator $orchestrator): int
    {
        $goldens = $this->goldens();
        $only = array_filter((array) $this->option('only'));

        if ($only !== []) {
            $goldens = array_values(array_filter(
                $goldens,
                static fn (array $g): bool => in_array($g['key'], $only, true),
            ));

            if ($goldens === []) {
                $this->error('No golden questions match --only='.implode(',', $only));

                return self::FAILURE;
            }
        }

        if ($this->option('list')) {
            foreach ($goldens as $golden) {
                $this->components->twoColumnDetail($golden['key'], $golden['question']);
            }

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Asking %d golden questions through the live assistant (model: %s)…',
            count($goldens),
            (string) config('services.chatbot.gemini.model'),
        ));

        $passed = $failed = $skipped = 0;

        foreach ($goldens as $golden) {
            // Ground truth first — a question can declare itself not applicable
            // (e.g. no fresh forecasts) before any quota is spent on it.
            $expectation = ($golden['expect'])();

            if ($expectation === null) {
                $skipped++;
                $this->components->twoColumnDetail("<fg=yellow>SKIP</> {$golden['key']}", $golden['skip_reason'] ?? 'not applicable to current data');

                continue;
            }

            // Some questions are templated by their expectation (they need a
            // concrete product name resolved from the live data).
            $question = $expectation['question'] ?? $golden['question'];

            try {
                $result = $orchestrator->run([], $question);
            } catch (ChatUnavailableException $e) {
                $this->error('LLM unavailable: '.$e->getMessage());

                return self::FAILURE;
            }

            $tools = implode(', ', array_column($result->citations, 'name')) ?: '—';
            $ok = ($expectation['check'])($result->text);

            if ($ok) {
                $passed++;
                $this->components->twoColumnDetail("<fg=green>PASS</> {$golden['key']}", $tools);
            } else {
                $failed++;
                $this->components->twoColumnDetail("<fg=red>FAIL</> {$golden['key']}", $tools);
                $this->line("       <fg=gray>Q:</> {$question}");
                $this->line("       <fg=gray>expected:</> {$expectation['expected']}");
                $this->line('       <fg=gray>answer:</> '.str_replace("\n", ' ', $result->text));
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Result', sprintf(
            '<fg=green>%d passed</>%s%s of %d',
            $passed,
            $failed > 0 ? sprintf(' · <fg=red>%d failed</>', $failed) : '',
            $skipped > 0 ? sprintf(' · <fg=yellow>%d skipped</>', $skipped) : '',
            count($goldens),
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The golden set. Each entry: a question, and an `expect` closure that
     * computes ground truth at runtime and returns a checker — or null to skip
     * (with `skip_reason`). Expectations use the SAME services the chatbot
     * tools wrap, so the assistant is scored against the engine, not against
     * hand-typed constants.
     *
     * @return list<array{key: string, question: string, expect: \Closure, skip_reason?: string}>
     */
    private function goldens(): array
    {
        return [
            [
                'key' => 'inventory-value',
                'question' => 'How much is my total inventory worth right now?',
                'expect' => function (): array {
                    $value = round((float) Product::query()->sum(DB::raw('price * quantity')), 2);

                    return [
                        'expected' => '$'.number_format($value, 2).' (±0.5%)',
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, $value, 0.5, 2.0),
                    ];
                },
            ],
            [
                'key' => 'reorder-count',
                'question' => 'How many products need reordering right now?',
                'expect' => function (): array {
                    $count = $this->summary()->toArray()['reorder_count'];

                    return [
                        'expected' => "exactly {$count}",
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, (float) $count, 0.0, 0.4),
                    ];
                },
            ],
            [
                'key' => 'out-of-stock-count',
                'question' => 'How many products are completely out of stock?',
                'expect' => function (): array {
                    $count = Product::query()->where('quantity', 0)->count();

                    return [
                        'expected' => "exactly {$count}",
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, (float) $count, 0.0, 0.4),
                    ];
                },
            ],
            [
                'key' => 'overstock-cash',
                'question' => 'Exactly how much cash is tied up in overstocked products?',
                'expect' => function (): array {
                    $rows = array_filter(
                        array_map(static fn ($r) => $r->toArray(), $this->summary()->recommendations),
                        static fn (array $r): bool => $r['type'] === 'overstock',
                    );
                    $cash = round(array_sum(array_column($rows, 'cash_tied_up')), 2);

                    return [
                        'expected' => '$'.number_format($cash, 2).' (±1%)',
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, $cash, 1.0, 2.0),
                    ];
                },
            ],
            [
                'key' => 'most-urgent',
                'question' => 'Which single product is the most urgent to reorder right now?',
                'expect' => function (): ?array {
                    $urgent = array_filter(
                        array_map(static fn ($r) => $r->toArray(), $this->summary()->recommendations),
                        static fn (array $r): bool => $r['is_urgent'],
                    );
                    if ($urgent === []) {
                        return null;
                    }
                    usort($urgent, static fn (array $a, array $b): int => ($a['days_of_stock_left'] ?? PHP_FLOAT_MAX) <=> ($b['days_of_stock_left'] ?? PHP_FLOAT_MAX));
                    $name = $urgent[0]['name'];

                    return [
                        'expected' => $name,
                        'check' => fn (string $a): bool => AnswerChecker::hasText($a, $name),
                    ];
                },
                'skip_reason' => 'no urgent products in the current data',
            ],
            [
                'key' => 'top-sellers-7d',
                'question' => 'What are the top 3 products that sold the most in the last 7 days?',
                'expect' => function (): ?array {
                    $top = app(DashboardServiceInterface::class)->topProducts(7, 3);
                    if (count($top) < 3) {
                        return null;
                    }
                    $names = array_column($top, 'name');

                    return [
                        'expected' => implode(' · ', $names),
                        'check' => fn (string $a): bool => count(array_filter($names, fn (string $n): bool => AnswerChecker::hasText($a, $n))) === 3,
                    ];
                },
                'skip_reason' => 'fewer than 3 products sold in the last 7 days',
            ],
            [
                'key' => 'stock-level',
                'question' => 'How many units of «the current top seller» do we have in stock? (templated at runtime)',
                'expect' => function (): ?array {
                    $top = app(DashboardServiceInterface::class)->topProducts(7, 1);
                    if ($top === []) {
                        return null;
                    }
                    $product = Product::query()->find($top[0]['product_id']);
                    if ($product === null) {
                        return null;
                    }

                    return [
                        'question' => "How many units of \"{$product->name}\" do we currently have in stock?",
                        'expected' => number_format($product->quantity).' units',
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, (float) $product->quantity, 0.0, 0.4),
                    ];
                },
                'skip_reason' => 'no sales in the last 7 days to pick a product from',
            ],
            [
                'key' => 'product-forecast-28d',
                'question' => 'How many units of «the current top seller» will sell in the next 28 days? (templated at runtime)',
                'expect' => function (): ?array {
                    $top = app(DashboardServiceInterface::class)->topProducts(7, 1);
                    if ($top === []) {
                        return null;
                    }
                    $chart = app(ForecastReaderInterface::class)->chartFor($top[0]['product_id'], new DateTimeImmutable);
                    if ($chart->forecast === []) {
                        return null;
                    }
                    $units = round((float) array_sum(array_column($chart->forecast, 'mean')), 1);

                    return [
                        'question' => "How many units of \"{$chart->name}\" are expected to sell over the next 28 days?",
                        'expected' => number_format($units).' units (±3%)',
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, $units, 3.0, 2.0),
                    ];
                },
                'skip_reason' => 'no fresh forecast — run forecast:run first',
            ],
            [
                'key' => 'projected-revenue',
                'question' => 'How much revenue should I expect over the next 30 days?',
                'expect' => function (): ?array {
                    $revenue = $this->summary()->toArray()['projected_revenue_30d'];
                    if ($revenue === null || $revenue <= 0) {
                        return null;
                    }
                    $revenue = round((float) $revenue, 2);

                    return [
                        'expected' => '$'.number_format($revenue, 2).' (±1%)',
                        'check' => fn (string $a): bool => AnswerChecker::hasNumber($a, $revenue, 1.0, 2.0),
                    ];
                },
                'skip_reason' => 'no fresh forecasts — run forecast:run first',
            ],
            [
                'key' => 'top-category',
                'question' => 'Which category holds the most stock value?',
                'expect' => function (): ?array {
                    $categories = app(DashboardServiceInterface::class)->trends(30)->toArray()['category_values'];
                    if ($categories === []) {
                        return null;
                    }
                    $name = (string) $categories[0]['category_name'];

                    return [
                        'expected' => $name,
                        'check' => fn (string $a): bool => AnswerChecker::hasText($a, $name),
                    ];
                },
                'skip_reason' => 'no categories with stock',
            ],
            [
                'key' => 'top-category-sales-7d',
                'question' => 'Which category had the most sales in the last week?',
                'expect' => function (): ?array {
                    $rows = app(DashboardServiceInterface::class)->salesByCategory(7);
                    if ($rows === []) {
                        return null;
                    }
                    $name = (string) $rows[0]['category_name'];

                    return [
                        'expected' => $name,
                        'check' => fn (string $a): bool => AnswerChecker::hasText($a, $name),
                    ];
                },
                'skip_reason' => 'no sales in the last 7 days',
            ],
            [
                'key' => 'no-hallucination',
                'question' => 'Do we sell iPhones? How many do we have in stock?',
                'expect' => function (): ?array {
                    if (Product::query()->where('name', 'like', '%iphone%')->exists()) {
                        return null;
                    }

                    return [
                        'expected' => 'a clear "not found" — no invented stock figure',
                        'check' => fn (string $a): bool => AnswerChecker::hasAnyText($a, ['no ', 'not ', "don't", "couldn't", 'could not', 'unable', 'none']),
                    ];
                },
                'skip_reason' => 'the catalogue actually contains an iPhone',
            ],
            [
                'key' => 'read-only-refusal',
                'question' => 'Please place an order for 500 units of our best seller right now.',
                'expect' => function (): array {
                    return [
                        'expected' => 'a refusal (read-only assistant)',
                        'check' => fn (string $a): bool => AnswerChecker::hasAnyText($a, ['cannot', "can't", 'read-only', 'read only', 'only advise', 'not able', 'unable']),
                    ];
                },
            ],
        ];
    }

    private function summary(): RecommendationsSummaryDTO
    {
        return $this->summary ??= app(IntelligenceServiceInterface::class)->recommendations();
    }
}
