<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Providers\AnthropicProvider;
use App\Domain\Ai\Providers\OpenAiProvider;
use App\Enums\AiProviderName;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class ProviderManager
{
    /**
     * Resolve a provider for a user. Selects per-user BYO key if present, otherwise platform key.
     */
    public function forName(AiProviderName $name, ?User $user = null): AiProvider
    {
        return match ($name) {
            AiProviderName::OpenAi => $this->makeOpenAi($user),
            AiProviderName::Anthropic => $this->makeAnthropic($user),
        };
    }

    /**
     * Resolve the user's currently-preferred provider, falling back to the configured default.
     */
    public function resolve(?User $user = null, ?string $name = null): AiProvider
    {
        $name = $name
            ?: ($user?->default_provider?->value ?? config('ai.default_provider', 'openai'));

        return $this->forName(AiProviderName::from($name), $user);
    }

    public function embeddingProvider(?User $user = null): AiProvider
    {
        // Embeddings always go through OpenAI for now — the cheapest and most stable option.
        return $this->makeOpenAi($user);
    }

    protected function makeOpenAi(?User $user): AiProvider
    {
        [$key, $byo] = $this->resolveKey('openai', $user);
        if (! $key) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }
        return new OpenAiProvider(
            apiKey: $key,
            baseUrl: config('ai.providers.openai.base_url'),
            usesByoKey: $byo,
        );
    }

    protected function makeAnthropic(?User $user): AiProvider
    {
        [$key, $byo] = $this->resolveKey('anthropic', $user);
        if (! $key) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }
        return new AnthropicProvider(
            apiKey: $key,
            baseUrl: config('ai.providers.anthropic.base_url'),
            apiVersion: config('ai.providers.anthropic.version', '2023-06-01'),
            usesByoKey: $byo,
            embeddingsFallback: $this->makeOpenAi($user),
        );
    }

    /**
     * @return array{0: ?string, 1: bool}  [apiKey, isByo]
     */
    protected function resolveKey(string $provider, ?User $user): array
    {
        if ($user) {
            $field = "byo_{$provider}_key_encrypted";
            if (! empty($user->{$field})) {
                try {
                    return [Crypt::decryptString($user->{$field}), true];
                } catch (\Throwable) {
                    // fall through to platform key
                }
            }
        }
        return [config("ai.providers.$provider.api_key"), false];
    }
}
