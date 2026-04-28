<?php

namespace App\Domain\Ai\Dto;

class ChatRequest
{
    /**
     * @param  ChatMessage[]  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $model,
        public readonly ?string $system = null,
        public readonly float $temperature = 0.3,
        public readonly int $maxTokens = 1024,
    ) {}
}
