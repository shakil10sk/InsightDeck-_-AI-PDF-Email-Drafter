<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Draft extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'parent_draft_id',
        'goal',
        'recipient',
        'tone',
        'length',
        'context',
        'output',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_draft_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_draft_id');
    }
}
