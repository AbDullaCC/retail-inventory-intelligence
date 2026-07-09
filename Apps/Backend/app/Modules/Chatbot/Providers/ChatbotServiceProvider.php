<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Providers;

use App\Modules\Chatbot\Services\ChatbotOrchestrator;
use App\Modules\Chatbot\Services\ChatbotService;
use App\Modules\Chatbot\Services\Contracts\ChatbotServiceInterface;
use App\Modules\Chatbot\Services\Contracts\ChatbotToolRegistryInterface;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Llm\GeminiLlmClient;
use App\Modules\Chatbot\Services\Llm\LlmClientFactory;
use App\Modules\Chatbot\Services\Tools\ChatbotToolRegistry;
use App\Modules\Chatbot\Services\Tools\GetForecastSummaryTool;
use App\Modules\Chatbot\Services\Tools\GetProductForecastTool;
use App\Modules\Chatbot\Services\Tools\GetProductRecommendationTool;
use App\Modules\Chatbot\Services\Tools\GetProductTool;
use App\Modules\Chatbot\Services\Tools\GetRecentMovementsTool;
use App\Modules\Chatbot\Services\Tools\GetReorderRecommendationsTool;
use App\Modules\Chatbot\Services\Tools\GetSalesTrendsTool;
use App\Modules\Chatbot\Services\Tools\GetStoreOverviewTool;
use App\Modules\Chatbot\Services\Tools\SearchProductsTool;
use App\Modules\Chatbot\Support\SystemPromptBuilder;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class ChatbotServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmClientInterface::class, function (): LlmClientInterface {
            return $this->app->make(LlmClientFactory::class)->make();
        });

        $this->app->singleton(ChatbotToolRegistryInterface::class, function (): ChatbotToolRegistryInterface {
            $max = (int) config('services.chatbot.max_tool_result_items', 20);

            // The fixed, read-only tool set. Each tool closes over its resolved
            // service; the registry is immutable after construction.
            return new ChatbotToolRegistry([
                $this->app->make(GetStoreOverviewTool::class)->build(),
                $this->app->make(GetReorderRecommendationsTool::class, ['maxItems' => $max])->build(),
                $this->app->make(GetProductRecommendationTool::class)->build(),
                $this->app->make(GetForecastSummaryTool::class)->build(),
                $this->app->make(GetProductForecastTool::class)->build(),
                $this->app->make(SearchProductsTool::class, ['maxItems' => $max])->build(),
                $this->app->make(GetProductTool::class)->build(),
                $this->app->make(GetRecentMovementsTool::class, ['maxItems' => $max])->build(),
                $this->app->make(GetSalesTrendsTool::class, ['maxItems' => $max])->build(),
            ]);
        });

        $this->app->bind(ChatbotServiceInterface::class, function (): ChatbotServiceInterface {
            return new ChatbotService(
                $this->app->make(ChatbotOrchestrator::class),
                (int) config('services.chatbot.max_history_messages', 12),
                (int) config('services.chatbot.rate_limit_per_hour', 30),
            );
        });

        $this->app->bind(ChatbotOrchestrator::class, function (): ChatbotOrchestrator {
            return new ChatbotOrchestrator(
                $this->app->make(LlmClientInterface::class),
                $this->app->make(ChatbotToolRegistryInterface::class),
                $this->app->make(SystemPromptBuilder::class),
                (int) config('services.chatbot.max_tool_iterations', 5),
            );
        });
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
