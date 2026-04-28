<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations ?? [],
            'prompt_tokens' => (int) $this->prompt_tokens,
            'completion_tokens' => (int) $this->completion_tokens,
            'cost_usd' => (float) $this->cost_usd,
            'model' => $this->model,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
