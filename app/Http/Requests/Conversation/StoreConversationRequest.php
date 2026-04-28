<?php

namespace App\Http\Requests\Conversation;

use App\Models\Document;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|nullable|string|max:200',
            'provider' => 'sometimes|in:openai,anthropic',
            'model' => 'sometimes|string|max:80',
            'system_prompt' => 'sometimes|nullable|string|max:4000',
            'document_ids' => 'sometimes|array|max:10',
            'document_ids.*' => 'integer',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $ids = $this->input('document_ids', []);
            if (! $ids) return;
            $count = Document::query()->whereIn('id', $ids)->count();
            if ($count !== count($ids)) {
                $v->errors()->add('document_ids', 'One or more documents are unavailable.');
            }
        });
    }
}
