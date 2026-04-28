<?php

namespace App\Models;

use App\Enums\AiProviderName;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'provider',
        'model',
        'system_prompt',
        'pinned_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'provider' => AiProviderName::class,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'conversation_documents')->withTimestamps();
    }
}
