<?php

namespace App\Domain\Ai\Dto;

class ChatChunk
{
    public function __construct(
        public readonly string $delta,
        public readonly bool $done = false,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}
}
