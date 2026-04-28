<?php

namespace App\Models;

use App\Enums\UsageAction;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'used_byo_key',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'action' => UsageAction::class,
            'used_byo_key' => 'boolean',
            'cost_usd' => 'decimal:6',
        ];
    }
}
