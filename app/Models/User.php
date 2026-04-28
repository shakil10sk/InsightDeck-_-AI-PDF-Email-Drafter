<?php

namespace App\Models;

use App\Enums\AiProviderName;
use App\Enums\PlanTier;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'plan_tier',
        'default_provider',
        'default_model',
        'byo_openai_key_encrypted',
        'byo_anthropic_key_encrypted',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'byo_openai_key_encrypted',
        'byo_anthropic_key_encrypted',
    ];

    protected $appends = [
        'has_byo_openai_key',
        'has_byo_anthropic_key',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_tier' => PlanTier::class,
            'default_provider' => AiProviderName::class,
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    protected function hasByoOpenaiKey(): Attribute
    {
        return Attribute::get(fn () => ! empty($this->byo_openai_key_encrypted));
    }

    protected function hasByoAnthropicKey(): Attribute
    {
        return Attribute::get(fn () => ! empty($this->byo_anthropic_key_encrypted));
    }
}
