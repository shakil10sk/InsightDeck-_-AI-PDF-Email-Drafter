<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_provider' => 'sometimes|in:openai,anthropic',
            'default_model' => 'sometimes|string|max:80',
            'byo_openai_key' => 'sometimes|nullable|string|max:200',
            'byo_anthropic_key' => 'sometimes|nullable|string|max:200',
        ];
    }
}
