<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * What a tool hands back to the conversation engine — always structured
 * data, never a raw Eloquent model (architecture doc Part II §4: "never
 * expose internal models directly"). Serialized to JSON and fed back to the
 * LLM as the tool's result content.
 */
final class AIToolResult
{
    private function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function ok(array $data): self
    {
        return new self(true, $data, null);
    }

    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $data, $message);
    }

    public function toArray(): array
    {
        return $this->success
            ? $this->data
            : ['error' => $this->errorMessage] + $this->data;
    }
}
