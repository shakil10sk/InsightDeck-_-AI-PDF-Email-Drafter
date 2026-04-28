<?php

namespace App\Domain\Rag\Services;

use App\Domain\Ai\Dto\ChatMessage;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\ProviderManager;
use App\Domain\Ai\TokenCostCalculator;
use App\Enums\AiProviderName;
use App\Enums\UsageAction;
use App\Jobs\GenerateConversationTitle;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Closure;
use Throwable;

class ChatStreamer
{
    public function __construct(
        protected ProviderManager $providers,
        protected Retriever $retriever,
        protected PromptBuilder $prompts,
        protected CitationFormatter $citations,
        protected UsageLogger $usage,
        protected TokenCostCalculator $cost,
    ) {}

    public function stream(
        Conversation $conversation,
        Message $userMessage,
        User $requestUser,
        Closure $onCitations,
        Closure $onChunk,
        Closure $onDone,
        Closure $onError,
    ): void {
        try {
            $providerName = $conversation->provider instanceof AiProviderName
                ? $conversation->provider
                : AiProviderName::tryFrom((string) $conversation->provider) ?? AiProviderName::OpenAi;

            $provider = $this->providers->forName($providerName, $requestUser);
            $embedder = $this->providers->embeddingProvider($requestUser);

            $documentIds = $conversation->documents()->pluck('documents.id')->all();
            $retrieved = [];
            $manifest = [];

            if ($documentIds) {
                $embedding = $embedder->embed([$userMessage->content]);
                $this->usage->record(
                    userId: $requestUser->id,
                    action: UsageAction::Embed->value,
                    provider: $embedder->name(),
                    model: $embedding->model,
                    promptTokens: $embedding->tokensUsed,
                    completionTokens: 0,
                    usedByoKey: $embedder->usesByoKey(),
                    relatedType: Conversation::class,
                    relatedId: $conversation->id,
                );

                $retrieved = $this->retriever->topK(
                    $embedding->embeddings[0] ?? [],
                    $documentIds,
                    (int) config('ai.retrieval.top_k', 6),
                );
                $manifest = $this->citations->manifest($retrieved);
                $onCitations($manifest);
            }

            $context = $this->prompts->buildContext($retrieved);
            $system = $this->prompts->systemPrompt()
                .($conversation->system_prompt ? "\n\nADDITIONAL INSTRUCTIONS:\n".$conversation->system_prompt : '')
                ."\n\n".$context;

            $history = $this->prompts->historyMessages($conversation, maxTurns: 10);
            // The user message we just persisted is included in the history.

            // Strip provider prefix if model is stored as "openai:gpt-4o-mini".
            $modelName = $conversation->model;
            if (str_contains((string) $modelName, ':')) {
                $modelName = explode(':', $modelName, 2)[1];
            }

            $chatRequest = new ChatRequest(
                messages: $history,
                model: $modelName,
                system: $system,
                temperature: 0.3,
                maxTokens: 1024,
            );

            $assistant = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => '',
                'provider' => $provider->name(),
                'model' => $modelName,
                'status' => 'streaming',
            ]);

            $full = '';
            $promptTokens = null;
            $completionTokens = null;

            foreach ($provider->streamChat($chatRequest) as $chunk) {
                if ($chunk->done) {
                    $promptTokens = $chunk->promptTokens;
                    $completionTokens = $chunk->completionTokens;
                    continue;
                }
                if ($chunk->delta !== '') {
                    $full .= $chunk->delta;
                    $onChunk($chunk->delta);
                }
                if (connection_aborted()) {
                    $assistant->forceFill([
                        'content' => $full,
                        'status' => 'cancelled',
                    ])->save();
                    return;
                }
            }

            $promptTokens ??= $this->cost->estimateTokens($system."\n".collect($history)->map->content->join("\n"));
            $completionTokens ??= $this->cost->estimateTokens($full);

            $usedCitations = $this->citations->inUseFrom($full, $manifest);

            $assistant->forceFill([
                'content' => $full,
                'citations' => $usedCitations,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'cost_usd' => $this->cost->cost($provider->name(), $modelName, $promptTokens, $completionTokens),
                'status' => 'complete',
            ])->save();

            $this->usage->record(
                userId: $requestUser->id,
                action: UsageAction::Chat->value,
                provider: $provider->name(),
                model: $modelName,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                usedByoKey: $provider->usesByoKey(),
                relatedType: Message::class,
                relatedId: $assistant->id,
            );

            $conversation->touch();

            // Auto-title after the first exchange.
            if ($conversation->messages()->count() <= 2 && $conversation->title === 'New conversation') {
                GenerateConversationTitle::dispatch($conversation->id);
            }

            $onDone($assistant->fresh());
        } catch (Throwable $e) {
            report($e);
            $onError($e);
        }
    }
}
