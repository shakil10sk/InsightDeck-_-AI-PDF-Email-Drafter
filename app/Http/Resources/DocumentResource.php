<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'page_count' => $this->page_count,
            'chunk_count' => $this->whenCounted('chunks'),
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'summaries' => SummaryResource::collection($this->whenLoaded('summaries')),
        ];
    }
}
