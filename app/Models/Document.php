<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'original_filename',
        'mime_type',
        'size_bytes',
        'page_count',
        'status',
        'error_message',
        'storage_disk',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_documents')->withTimestamps();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill(['status' => DocumentStatus::Failed, 'error_message' => $reason])->save();
    }
}
