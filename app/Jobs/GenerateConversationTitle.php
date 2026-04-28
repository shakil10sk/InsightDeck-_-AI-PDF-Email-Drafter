<?php

namespace App\Jobs;

use App\Domain\Ai\Dto\ChatMessage;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\ProviderManager;
use App\Domain\Ai\TokenCostCalculator;
use App\Domain\Rag\Services\UsageLogger;
use App\Enums\AiProviderName;
use App\Enums\UsageAction;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateConversationTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $conversationId) {}

    public function handle(ProviderManager $providers, UsageLogger $usage, TokenCostCalculator $cost): void
    {
        $conversation = Conversation::query()->withoutGlobalScopes()->find($this->conversationId);
        if (! $conversation) return;

        $first = $conversation->messages()->orderBy('created_at')->first();
        if (! $first) return;

        $providerEnum = AiProviderName::tryFrom((string) $conversation->provider) ?? AiProviderName::OpenAi;
        $provider = $providers->forName($providerEnum, $conversation->user);

        // Use a cheap model regardless of conversation model.
        $cheap = match ($providerEnum) {
            AiProviderName::OpenAi => 'gpt-4o-mini',
            AiProviderName::Anthropic => 'claude-haiku-4-5',
        };

        $req = new ChatRequest(
            messages: [ChatMessage::user("Suggest a 3-6 word title (no quotes, plain text) for a conversation that begins with: \"{$first->content}\"")],
            model: $cheap,
            system: 'You generate short, descriptive chat titles. Reply with just the title text — no quotes, no punctuation at the end.',
            temperature: 0.5,
            maxTokens: 24,
        );

        $result = $provider->chat($req);
        $title = trim($result->delta);
        $title = trim($title, "\"' .");
        if ($title === '') return;

        $conversation->forceFill(['title' => mb_substr($title, 0, 80)])->save();

        $pt = $result->promptTokens ?? $cost->estimateTokens($first->content);
        $ct = $result->completionTokens ?? $cost->estimateTokens($title);

        $usage->record(
            userId: $conversation->user_id,
            action: UsageAction::Title->value,
            provider: $provider->name(),
            model: $cheap,
            promptTokens: $pt,
            completionTokens: $ct,
            usedByoKey: $provider->usesByoKey(),
            relatedType: Conversation::class,
            relatedId: $conversation->id,
        );
    }
}
