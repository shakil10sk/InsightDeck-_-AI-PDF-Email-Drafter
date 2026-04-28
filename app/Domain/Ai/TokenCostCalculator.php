<?php

namespace App\Domain\Ai;

class TokenCostCalculator
{
    public function priceFor(string $provider, string $model): array
    {
        $pricing = config("ai-pricing.$provider.$model");
        if ($pricing) {
            return $pricing;
        }
        return ['input' => 0.0, 'output' => 0.0];
    }

    public function cost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        $price = $this->priceFor($provider, $model);
        return round(
            ($promptTokens / 1000) * (float) ($price['input'] ?? 0)
            + ($completionTokens / 1000) * (float) ($price['output'] ?? 0),
            6,
        );
    }

    public function estimateTokens(string $text): int
    {
        // Conservative ≈ 4 chars per token. Real tokenizers vary; this is fine for budgeting.
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
