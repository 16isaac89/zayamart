<?php

namespace Modules\AIAssistant\app\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AIAssistant\app\Console\Commands\BackfillKnowledgeEmbeddingsCommand;
use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;
use Modules\AIAssistant\app\Knowledge\Extractors\CsvTextExtractor;
use Modules\AIAssistant\app\Knowledge\Extractors\DocxTextExtractor;
use Modules\AIAssistant\app\Knowledge\Extractors\PdfTextExtractor;
use Modules\AIAssistant\app\Knowledge\Extractors\PlainTextExtractor;
use Modules\AIAssistant\app\Knowledge\NullEmbeddingProvider;
use Modules\AIAssistant\app\Knowledge\TextChunker;
use Modules\AIAssistant\app\Services\AIProviderManager;
use Modules\AIAssistant\app\Services\KnowledgeIngestionService;
use Modules\AIAssistant\app\Tools\AddToCartTool;
use Modules\AIAssistant\app\Tools\CalculateDeliveryTool;
use Modules\AIAssistant\app\Tools\CheckStockTool;
use Modules\AIAssistant\app\Tools\CreateOrderTool;
use Modules\AIAssistant\app\Tools\GetCartTool;
use Modules\AIAssistant\app\Tools\GetOrderStatusTool;
use Modules\AIAssistant\app\Tools\GetProductTool;
use Modules\AIAssistant\app\Tools\GetProductVariantsTool;
use Modules\AIAssistant\app\Tools\GetRealEstateListingTool;
use Modules\AIAssistant\app\Tools\RemoveFromCartTool;
use Modules\AIAssistant\app\Tools\SearchKnowledgeBaseTool;
use Modules\AIAssistant\app\Tools\SearchProductsTool;
use Modules\AIAssistant\app\Tools\SearchRealEstateListingsTool;
use Modules\AIAssistant\app\Tools\SubmitRealEstateInquiryTool;
use Modules\AIAssistant\app\Tools\StartCheckoutTool;
use Modules\AIAssistant\app\Tools\ToolRegistry;
use Modules\AIAssistant\app\Tools\UpdateCartTool;
use Modules\AIAssistant\app\Tools\WhatsAppInquiryTool;
use Modules\AIAssistant\AIProviders\AnthropicProvider;
use Modules\AIAssistant\AIProviders\DeepSeekProvider;
use Modules\AIAssistant\AIProviders\OpenAIProvider;

class AIAssistantServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AIAssistant';

    protected string $moduleNameLower = 'aiassistant';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillKnowledgeEmbeddingsCommand::class]);
        }
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), $this->moduleNameLower);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Every concrete provider adapter implements the same, provider-neutral
        // AIProviderInterface (see app/Contracts/AIProviderInterface.php). The
        // manager is what the rest of the app depends on — nothing outside this
        // list should ever `new` a concrete provider directly.
        $this->app->singleton(AIProviderManager::class, function ($app) {
            return new AIProviderManager([
                $app->make(DeepSeekProvider::class),
                $app->make(OpenAIProvider::class),
                $app->make(AnthropicProvider::class),
                // A future provider (Gemini, ...) is added here only —
                // ConversationService, ToolRegistry, and every tool are
                // unaffected. See architecture doc Part II §1.
            ]);
        });

        // One application-level tool per marketplace capability — never one
        // per provider. See architecture doc Part II §4.
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry([
                $app->make(SearchProductsTool::class),
                $app->make(GetProductTool::class),
                $app->make(GetProductVariantsTool::class),
                $app->make(CheckStockTool::class),
                $app->make(GetCartTool::class),
                $app->make(AddToCartTool::class),
                $app->make(UpdateCartTool::class),
                $app->make(RemoveFromCartTool::class),
                $app->make(CalculateDeliveryTool::class),
                $app->make(StartCheckoutTool::class),
                $app->make(CreateOrderTool::class),
                $app->make(GetOrderStatusTool::class),
                $app->make(SearchKnowledgeBaseTool::class),
                $app->make(WhatsAppInquiryTool::class),
                $app->make(SearchRealEstateListingsTool::class),
                $app->make(GetRealEstateListingTool::class),
                $app->make(SubmitRealEstateInquiryTool::class),
            ]);
        });

        // Deliberately NOT OpenAIEmbeddingProvider yet: this platform runs
        // vendors on DeepSeek, which has no embeddings endpoint, so real
        // embeddings would mean a *second*, OpenAI-billed API call per
        // knowledge-base chunk/query for every vendor on the platform — a
        // real per-vendor cost with no budget allocated yet. Swap this
        // binding to OpenAIEmbeddingProvider::class (and set OPENAI_API_KEY)
        // when that's funded; KnowledgeRetrievalService and
        // KnowledgeIngestionService need no further changes to pick it up,
        // and app/Console/Commands/BackfillKnowledgeEmbeddingsCommand
        // handles chunks stored before the switch. Until then, retrieval is
        // MariaDB FULLTEXT/LIKE search only — see KnowledgeRetrievalService.
        $this->app->singleton(KnowledgeEmbeddingProviderInterface::class, NullEmbeddingProvider::class);

        $this->app->singleton(KnowledgeIngestionService::class, function ($app) {
            return new KnowledgeIngestionService(
                extractors: [
                    $app->make(PlainTextExtractor::class),
                    $app->make(CsvTextExtractor::class),
                    $app->make(PdfTextExtractor::class),
                    $app->make(DocxTextExtractor::class),
                ],
                chunker: $app->make(TextChunker::class),
                embeddingProvider: $app->make(KnowledgeEmbeddingProviderInterface::class),
            );
        });
    }

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [AIProviderManager::class, ToolRegistry::class];
    }
}
