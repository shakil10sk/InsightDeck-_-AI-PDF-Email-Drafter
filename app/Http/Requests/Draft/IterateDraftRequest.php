<?php

namespace App\Http\Requests\Draft;

use Illuminate\Foundation\Http\FormRequest;

class IterateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instruction' => 'required|string|max:1000',
            'tone' => 'sometimes|in:friendly,formal,direct,empathetic',
            'length' => 'sometimes|in:short,medium,long',
        ];
    }
}
