<?php

namespace App\Domain\Drafting\Services;

use App\Domain\Ai\Dto\ChatMessage;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\ProviderManager;
use App\Domain\Ai\TokenCostCalculator;
use App\Domain\Rag\Services\UsageLogger;
use App\Enums\AiProviderName;
use App\Enums\UsageAction;
use App\Models\Draft;
use App\Models\User;
use Closure;
use Throwable;

class DraftStreamer
{
    public function __construct(
        protected ProviderManager $providers,
        protected UsageLogger $usage,
        protected TokenCostCalculator $cost,
    ) {}

    public function stream(
        Draft $draft,
        User $user,
        Closure $onChunk,
        Closure $onDone,
        Closure $onError,
    ): void {
        try {
            $providerEnum = AiProviderName::tryFrom((string) $draft->provider) ?? AiProviderName::OpenAi;
            $provider = $this->providers->forName($providerEnum, $user);

            $system = $this->systemPrompt();
            $userPrompt = $this->buildPrompt($draft);

            $req = new ChatRequest(
                messages: [ChatMessage::user($userPrompt)],
                model: $draft->model,
                system: $system,
                temperature: 0.6,
                maxTokens: $draft->length === 'long' ? 1024 : 600,
            );

            $full = '';
            $pt = null;
            $ct = null;

            foreach ($provider->streamChat($req) as $chunk) {
                if ($chunk->done) {
                    $pt = $chunk->promptTokens;
                    $ct = $chunk->completionTokens;
                    continue;
                }
                if ($chunk->delta !== '') {
                    $full .= $chunk->delta;
                    $onChunk($chunk->delta);
                }
                if (connection_aborted()) break;
            }

            $pt ??= $this->cost->estimateTokens($system."\n".$userPrompt);
            $ct ??= $this->cost->estimateTokens($full);
            $cost = $this->cost->cost($provider->name(), $draft->model, $pt, $ct);

            $draft->forceFill([
                'output' => $full,
                'prompt_tokens' => $pt,
                'completion_tokens' => $ct,
                'cost_usd' => $cost,
            ])->save();

            $this->usage->record(
                userId: $user->id,
                action: UsageAction::Draft->value,
                provider: $provider->name(),
                model: $draft->model,
                promptTokens: $pt,
                completionTokens: $ct,
                usedByoKey: $provider->usesByoKey(),
                relatedType: Draft::class,
                relatedId: $draft->id,
            );

            $onDone($draft->fresh());
        } catch (Throwable $e) {
            report($e);
            $onError($e);
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'TXT'
You are a professional email drafter. Produce a single email body — no subject line, no preamble, no closing meta-comments. Use the requested tone and length. Use first person. If a Recipient is provided, address them appropriately.
TXT;
    }

    protected function buildPrompt(Draft $draft): string
    {
        $parts = [];
        $parts[] = "GOAL: {$draft->goal}";
        if ($draft->recipient) $parts[] = "RECIPIENT: {$draft->recipient}";
        $parts[] = "TONE: {$draft->tone}";
        $parts[] = "LENGTH: {$draft->length}";
        if ($draft->context) $parts[] = "CONTEXT (existing thread or notes):\n{$draft->context}";
        return implode("\n\n", $parts);
    }
}
