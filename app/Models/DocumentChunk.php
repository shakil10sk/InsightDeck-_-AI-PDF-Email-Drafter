<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'page_number',
        'content',
        'embedding',
        'token_count',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function snippet(int $length = 80): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string) $this->content));
        return mb_strlen($clean) > $length
            ? mb_substr($clean, 0, $length).'…'
            : $clean;
    }
}
