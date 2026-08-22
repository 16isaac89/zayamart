<?php

namespace Modules\AIAssistant\app\Services;

use Modules\AIAssistant\app\DataTransfer\AIChatTurnResult;
use Modules\AIAssistant\app\DataTransfer\AIToolCall;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Exceptions\AIProviderException;
use Modules\AIAssistant\app\Jobs\RecordAIUsageJob;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Models\AIMessage;
use Modules\AIAssistant\app\Models\AiToolCallLog;
use Modules\AIAssistant\app\Tools\ToolRegistry;

/**
 * One chat turn: persist the customer's message, call the provider (via
 * AIProviderManager only — never a concrete provider class), execute any
 * tool calls it requests, and persist the assistant's reply. Runs
 * synchronously inside the authenticated request handling this turn — see
 * architecture doc Part II §10/§13 for why tool execution can't be deferred
 * to a queue.
 *
 * Handling mode (brief §8): when the conversation is human_active, or the
 * agent's handling_mode is 'human', the AI never generates a reply — the
 * customer's message is persisted and the turn ends. HandoffService decides
 * whether a customer message should escalate a hybrid/AI conversation.
 *
 * Note on capability degradation (architecture doc Part II §2): a provider
 * without tool_calling simply never receives tools (AIProviderManager
 * already omits them), which means it can only serve plain conversation —
 * pre-fetching tool results in PHP for such a provider is real future work,
 * not implemented in this first pass.
 */
class ConversationService
{
    private const MAX_TOOL_ITERATIONS = 5;

    /** Which structured envelope type a given tool result maps to (brief §12). */
    private const TOOL_RESULT_TYPES = [
        'search_products' => AIChatTurnResult::TYPE_PRODUCT_LIST,
        'get_product' => AIChatTurnResult::TYPE_PRODUCT,
        'get_product_variants' => AIChatTurnResult::TYPE_PRODUCT,
        'get_cart' => AIChatTurnResult::TYPE_CART,
        'add_to_cart' => AIChatTurnResult::TYPE_CART,
        'update_cart' => AIChatTurnResult::TYPE_CART,
        'remove_from_cart' => AIChatTurnResult::TYPE_CART,
        'start_checkout' => AIChatTurnResult::TYPE_CHECKOUT,
        'create_order' => AIChatTurnResult::TYPE_CONFIRMATION,
        'get_order_status' => AIChatTurnResult::TYPE_ORDER_SUMMARY,
    ];

    public function __construct(
        private readonly AIProviderManager $providerManager,
        private readonly ToolRegistry $tools,
        private readonly PromptBuilder $promptBuilder,
        private readonly HandoffService $handoffService,
    ) {
    }

    public function handleUserMessage(AIConversation $conversation, string $userText, ToolExecutionContext $context): AIChatTurnResult
    {
        $agent = $conversation->agent;

        AIMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AIMessage::ROLE_USER,
            'content' => $userText,
            'sender_type' => AIMessage::SENDER_CUSTOMER,
        ]);

        if ($this->handoffService->shouldRequestHuman($conversation, $userText)) {
            $this->handoffService->requestHuman($conversation);
        }

        $conversation->refresh();

        // AI never speaks once a human is active, or for agents configured
        // fully human — the customer's message is recorded and the human
        // inbox (support_status) is the only thing that changes.
        if ($conversation->isHumanActive() || $agent->handling_mode === AIAgent::HANDLING_HUMAN) {
            return new AIChatTurnResult(
                reply: null,
                type: AIChatTurnResult::TYPE_HANDOFF,
                data: [],
                supportStatus: $conversation->support_status,
                handlingMode: $agent->handling_mode,
                conversationId: $conversation->id,
            );
        }

        if ($conversation->needsAttention()) {
            return new AIChatTurnResult(
                reply: "I've let the team know you'd like to speak with someone — they'll join shortly.",
                type: AIChatTurnResult::TYPE_HANDOFF,
                data: [],
                supportStatus: $conversation->support_status,
                handlingMode: $agent->handling_mode,
                conversationId: $conversation->id,
            );
        }

        try {
            $resolved = $this->providerManager->resolveForAgent($agent);
        } catch (AIProviderException $exception) {
            return new AIChatTurnResult(
                reply: 'This assistant is not configured yet — please contact the vendor directly.',
                type: AIChatTurnResult::TYPE_ERROR,
                data: [],
                supportStatus: $conversation->support_status,
                handlingMode: $agent->handling_mode,
                conversationId: $conversation->id,
            );
        }

        $systemPrompt = $this->promptBuilder->build($agent);
        $messages = $this->historyAsChatMessages($conversation);
        $toolDefinitions = $this->tools->definitions();
        $lastToolName = null;
        $lastToolResult = null;

        for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
            try {
                $request = $this->providerManager->createRequestFor($resolved, $systemPrompt, $messages, $toolDefinitions);
                $response = $resolved->provider->chat($request);
            } catch (AIProviderException $exception) {
                return new AIChatTurnResult(
                    reply: "Sorry, I'm having trouble responding right now. Please try again shortly.",
                    type: AIChatTurnResult::TYPE_ERROR,
                    data: [],
                    supportStatus: $conversation->support_status,
                    handlingMode: $agent->handling_mode,
                    conversationId: $conversation->id,
                );
            }

            if ($response->usage) {
                RecordAIUsageJob::dispatch(
                    $conversation->seller_id,
                    $conversation->id,
                    $resolved->billingMode,
                    $resolved->aiProviderId,
                    $resolved->aiProviderModelId,
                    $resolved->vendorAiProviderId,
                    $response->usage->inputTokens,
                    $response->usage->outputTokens,
                    $response->usage->cachedTokens,
                    $response->usage->estimated,
                )->onConnection(config('aiassistant.queue_connection'));
            }

            if (!$response->hasToolCalls()) {
                AIMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => AIMessage::ROLE_ASSISTANT,
                    'content' => $response->content,
                    'sender_type' => AIMessage::SENDER_AI,
                    'sender_name' => $agent->displayName(),
                ]);

                return $this->buildResult($conversation, $agent, (string)$response->content, $lastToolName, $lastToolResult);
            }

            $assistantMessage = AIMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => AIMessage::ROLE_ASSISTANT,
                'content' => $response->content,
                'sender_type' => AIMessage::SENDER_AI,
                'sender_name' => $agent->displayName(),
            ]);

            $messages[] = ChatMessage::assistant($response->content, $response->toolCalls);

            foreach ($response->toolCalls as $toolCall) {
                /** @var AIToolCall $toolCall */
                $result = $this->tools->execute($toolCall->name, $toolCall->arguments, $context);
                $lastToolName = $toolCall->name;
                $lastToolResult = $result;

                AiToolCallLog::create([
                    'ai_message_id' => $assistantMessage->id,
                    'tool_name' => $toolCall->name,
                    'arguments' => $toolCall->arguments,
                    'result' => $result->toArray(),
                    'status' => $result->success ? AiToolCallLog::STATUS_OK : AiToolCallLog::STATUS_ERROR,
                ]);

                $messages[] = ChatMessage::toolResult($toolCall->id, json_encode($result->toArray()));
            }
        }

        return new AIChatTurnResult(
            reply: "I wasn't able to finish that — could you rephrase, or would you like to speak with the vendor directly?",
            type: AIChatTurnResult::TYPE_TEXT,
            data: [],
            supportStatus: $conversation->support_status,
            handlingMode: $agent->handling_mode,
            conversationId: $conversation->id,
        );
    }

    private function buildResult(AIConversation $conversation, AIAgent $agent, string $reply, ?string $lastToolName, ?AIToolResult $lastToolResult): AIChatTurnResult
    {
        $type = $lastToolName ? (self::TOOL_RESULT_TYPES[$lastToolName] ?? AIChatTurnResult::TYPE_TEXT) : AIChatTurnResult::TYPE_TEXT;
        $data = ($lastToolResult && $lastToolResult->success) ? $lastToolResult->data : [];

        $conversation->refresh();

        return new AIChatTurnResult(
            reply: $reply,
            type: $type,
            data: $data,
            supportStatus: $conversation->support_status,
            handlingMode: $agent->handling_mode,
            conversationId: $conversation->id,
        );
    }

    /**
     * @return ChatMessage[]
     */
    private function historyAsChatMessages(AIConversation $conversation): array
    {
        $messages = [];

        foreach ($conversation->messages as $message) {
            /** @var AIMessage $message */
            if ($message->sender_type === AIMessage::SENDER_SYSTEM) {
                continue; // internal notices only, never replayed to the LLM
            }

            if ($message->role === AIMessage::ROLE_ASSISTANT) {
                // The persisted log id, not the provider's original tool_call
                // id, is reused as the correlation id on replay — see
                // ConversationService's original docblock note (unchanged
                // from Part II).
                $toolCalls = $message->toolCalls->map(fn (AiToolCallLog $log) => new AIToolCall(
                    id: (string)$log->id,
                    name: $log->tool_name,
                    arguments: $log->arguments ?? [],
                ))->all();

                $messages[] = ChatMessage::assistant($message->content, $toolCalls);

                foreach ($message->toolCalls as $log) {
                    $messages[] = ChatMessage::toolResult((string)$log->id, json_encode($log->result));
                }
            } elseif ($message->role === AIMessage::ROLE_USER) {
                $messages[] = ChatMessage::user((string)$message->content);
            }
        }

        return $messages;
    }
}
