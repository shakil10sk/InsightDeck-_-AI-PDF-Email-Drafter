<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:200',
            'pinned' => 'sometimes|boolean',
            'provider' => 'sometimes|in:openai,anthropic',
            'model' => 'sometimes|string|max:80',
            'document_ids' => 'sometimes|array|max:10',
            'document_ids.*' => 'integer',
        ];
    }
}
