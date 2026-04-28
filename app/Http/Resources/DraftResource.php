<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_draft_id' => $this->parent_draft_id,
            'goal' => $this->goal,
            'recipient' => $this->recipient,
            'tone' => $this->tone,
            'length' => $this->length,
            'context' => $this->context,
            'output' => $this->output,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => (int) $this->prompt_tokens,
            'completion_tokens' => (int) $this->completion_tokens,
            'cost_usd' => (float) $this->cost_usd,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
