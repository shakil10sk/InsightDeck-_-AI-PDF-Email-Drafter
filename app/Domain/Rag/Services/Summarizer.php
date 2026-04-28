<?php

namespace App\Domain\Rag\Services;

use App\Domain\Ai\Dto\ChatMessage;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\ProviderManager;
use App\Domain\Ai\TokenCostCalculator;
use App\Enums\AiProviderName;
use App\Enums\UsageAction;
use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Closure;

class Summarizer
{
    public function __construct(
        protected ProviderManager $providers,
        protected UsageLogger $usage,
        protected TokenCostCalculator $cost,
    ) {}

    public function stream(
        Document $document,
        string $length,
        User $user,
        ?string $provider,
        ?string $model,
        Closure $onChunk,
        Closure $onDone,
    ): void {
        $provider = $provider ?: ($user->default_provider?->value ?? config('ai.default_provider'));
        $model = $model ?: ($user->default_model ?? config('ai.default_chat_model'));
        $aiProvider = $this->providers->forName(AiProviderName::from($provider), $user);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();
        $combined = $chunks->pluck('content')->implode("\n\n");

        // Truncate to fit context window — for portfolio scale this is fine; v2 can do map-reduce.
        $maxChars = 30000;
        if (mb_strlen($combined) > $maxChars) {
            $combined = mb_substr($combined, 0, $maxChars).'…';
        }

        $instructions = match ($length) {
            'short' => 'Write a 3-bullet summary capturing the most important points only.',
            'long' => 'Write a thorough summary, around 6-10 paragraphs, organized with Markdown headings for each major topic.',
            default => 'Write a balanced summary of around 250-400 words, using short paragraphs and bullets where helpful.',
        };

        $system = "You are a careful summarizer. $instructions Use only the provided document. Do not invent details.";

        $request = new ChatRequest(
            messages: [ChatMessage::user("DOCUMENT TITLE: {$document->title}\n\nCONTENT:\n$combined")],
            model: $model,
            system: $system,
            temperature: 0.2,
            maxTokens: $length === 'long' ? 2048 : 1024,
        );

        $full = '';
        $promptTokens = null;
        $completionTokens = null;

        foreach ($aiProvider->streamChat($request) as $chunk) {
            if ($chunk->done) {
                $promptTokens = $chunk->promptTokens;
                $completionTokens = $chunk->completionTokens;
                continue;
            }
            if ($chunk->delta !== '') {
                $full .= $chunk->delta;
                $onChunk($chunk->delta);
            }
        }

        $promptTokens ??= $this->cost->estimateTokens($system."\n".$combined);
        $completionTokens ??= $this->cost->estimateTokens($full);
        $totalCost = $this->cost->cost($aiProvider->name(), $model, $promptTokens, $completionTokens);

        $summary = Summary::query()->updateOrCreate(
            ['document_id' => $document->id, 'length' => $length],
            ['content' => $full, 'model' => $model, 'token_cost' => $promptTokens + $completionTokens]
        );

        $this->usage->record(
            userId: $user->id,
            action: UsageAction::Summarize->value,
            provider: $aiProvider->name(),
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            usedByoKey: $aiProvider->usesByoKey(),
            relatedType: Summary::class,
            relatedId: $summary->id,
        );

        $onDone([
            'summary' => [
                'id' => $summary->id,
                'length' => $summary->length,
                'content' => $summary->content,
                'model' => $summary->model,
                'token_cost' => $summary->token_cost,
            ],
            'cost_usd' => $totalCost,
        ]);
    }
}
