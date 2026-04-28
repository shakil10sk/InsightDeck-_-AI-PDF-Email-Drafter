<?php

namespace App\Http\Requests\Draft;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal' => 'required|string|max:2000',
            'recipient' => 'sometimes|nullable|string|max:200',
            'tone' => 'sometimes|in:friendly,formal,direct,empathetic',
            'length' => 'sometimes|in:short,medium,long',
            'context' => 'sometimes|nullable|string|max:8000',
            'provider' => 'sometimes|in:openai,anthropic',
            'model' => 'sometimes|string|max:80',
        ];
    }
}
