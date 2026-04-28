<?php

namespace App\Domain\Ai\Dto;

class EmbeddingResult
{
    /**
     * @param  array<int, array<int, float>>  $embeddings
     */
    public function __construct(
        public readonly array $embeddings,
        public readonly int $tokensUsed,
        public readonly string $model,
    ) {}
}
