<?php

declare(strict_types=1);

namespace App\Modules\Chatbot\Providers;

use App\Modules\Chatbot\Console\ChatbotEvaluateCommand;
use App\Modules\Chatbot\Services\ChatOrchestrator;
use App\Modules\Chatbot\Services\ChatService;
use App\Modules\Chatbot\Services\Contracts\ChatServiceInterface;
use App\Modules\Chatbot\Services\Contracts\LlmClientInterface;
use App\Modules\Chatbot\Services\Llm\GeminiClient;
use App\Modules\Chatbot\Services\Tools\FindProductTool;
use App\Modules\Chatbot\Services\Tools\GetProductForecastTool;
use App\Modules\Chatbot\Services\Tools\GetProductRecommendationTool;
use App\Modules\Chatbot\Services\Tools\GetRecentMovementsTool;
use App\Modules\Chatbot\Services\Tools\GetRecommendationsTool;
use App\Modules\Chatbot\Services\Tools\GetSalesTrendsTool;
use App\Modules\Chatbot\Services\Tools\GetStoreOverviewTool;
use App\Modules\Chatbot\Services\Tools\GetTopProductsTool;
use App\Modules\Chatbot\Services\Tools\ToolRegistry;
use App\Modules\Shared\Providers\ModuleServiceProvider;

final class ChatbotServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmClientInterface::class, function (): LlmClientInterface {
            $gemini = (array) config('services.chatbot.gemini');

            return new GeminiClient(
                apiKey: (string) ($gemini['api_key'] ?? ''),
                model: (string) ($gemini['model'] ?? 'gemini-3.1-flash-lite'),
                baseUrl: (string) ($gemini['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'),
                timeout: (int) ($gemini['timeout'] ?? 30),
                maxTokens: (int) config('services.chatbot.max_tokens', 2048),
                temperature: (float) config('services.chatbot.temperature', 0.2),
            );
        });

        $this->app->singleton(ToolRegistry::class, function (): ToolRegistry {
            $cap = (int) config('services.chatbot.max_tool_result_items', 20);

            // The assistant's complete, fixed capability set — read-only by
            // construction: every handler wraps an existing read service.
            return new ToolRegistry([
                $this->app->make(GetStoreOverviewTool::class)->build(),
                $this->app->make(GetRecommendationsTool::class, ['defaultLimit' => $cap])->build(),
                $this->app->make(FindProductTool::class)->build(),
                $this->app->make(GetProductRecommendationTool::class)->build(),
                $this->app->make(GetProductForecastTool::class)->build(),
                $this->app->make(GetTopProductsTool::class)->build(),
                $this->app->make(GetSalesTrendsTool::class)->build(),
                $this->app->make(GetRecentMovementsTool::class, ['defaultLimit' => $cap])->build(),
            ]);
        });

        $this->app->bind(ChatOrchestrator::class, function (): ChatOrchestrator {
            return new ChatOrchestrator(
                $this->app->make(LlmClientInterface::class),
                $this->app->make(ToolRegistry::class),
                (int) config('services.chatbot.max_tool_iterations', 5),
            );
        });

        $this->app->bind(ChatServiceInterface::class, function (): ChatServiceInterface {
            return new ChatService(
                $this->app->make(ChatOrchestrator::class),
                (int) config('services.chatbot.max_history_messages', 12),
                (int) config('services.chatbot.rate_limit_per_hour', 30),
            );
        });
    }

    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ChatbotEvaluateCommand::class]);
        }
    }
}
