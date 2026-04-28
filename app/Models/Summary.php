<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Summary extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'length',
        'content',
        'model',
        'token_cost',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
