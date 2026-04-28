<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'length' => $this->length,
            'content' => $this->content,
            'model' => $this->model,
            'token_cost' => (int) $this->token_cost,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
