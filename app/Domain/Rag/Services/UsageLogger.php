<?php

namespace App\Domain\Rag\Services;

use App\Domain\Ai\TokenCostCalculator;
use App\Models\UsageRecord;

class UsageLogger
{
    public function __construct(protected TokenCostCalculator $cost) {}

    public function record(
        int $userId,
        string $action,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        bool $usedByoKey = false,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): UsageRecord {
        $cost = $this->cost->cost($provider, $model, $promptTokens, $completionTokens);

        return UsageRecord::query()->withoutGlobalScopes()->create([
            'user_id' => $userId,
            'action' => $action,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'cost_usd' => $cost,
            'used_byo_key' => $usedByoKey,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }
}
