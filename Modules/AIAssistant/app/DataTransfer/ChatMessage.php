<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * One turn in the provider-neutral message history sent as part of an
 * AIChatRequest. Distinct from the persisted Models\AIMessage row — this is
 * the in-flight value object each provider adapter maps into its own wire
 * format.
 */
final class ChatMessage
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    public function __construct(
        public readonly string $role,
        public readonly ?string $content,
        /** Only present on assistant messages that requested tool calls. */
        public readonly array $toolCalls = [],
        /** Only present on tool-result messages — must match a prior AIToolCall::$id. */
        public readonly ?string $toolCallId = null,
    ) {
    }

    public static function user(string $content): self
    {
        return new self(self::ROLE_USER, $content);
    }

    public static function assistant(?string $content, array $toolCalls = []): self
    {
        return new self(self::ROLE_ASSISTANT, $content, $toolCalls);
    }

    public static function toolResult(string $toolCallId, string $content): self
    {
        return new self(self::ROLE_TOOL, $content, [], $toolCallId);
    }
}
