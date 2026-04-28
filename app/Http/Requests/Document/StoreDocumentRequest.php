<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('insightdeck.max_upload_mb', 20) * 1024;
        return [
            'file' => "required|file|mimes:pdf|max:{$maxKb}",
            'title' => 'sometimes|string|max:200',
        ];
    }
}
