<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'model' => $this->model,
            'system_prompt' => $this->system_prompt,
            'pinned_at' => $this->pinned_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'message_count' => $this->whenCounted('messages'),
        ];
    }
}
