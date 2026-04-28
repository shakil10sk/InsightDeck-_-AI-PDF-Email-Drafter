<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Dto\ChatChunk;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\Dto\EmbeddingResult;

interface AiProvider
{
    public function name(): string;

    /**
     * Stream a chat completion. Yields ChatChunk instances; the final yield must have done=true
     * and may include final token counts.
     *
     * @return iterable<ChatChunk>
     */
    public function streamChat(ChatRequest $request): iterable;

    /**
     * Synchronously complete a chat (used for short jobs like title generation).
     */
    public function chat(ChatRequest $request): ChatChunk;

    /**
     * Embed an array of strings.
     *
     * @param  string[]  $texts
     */
    public function embed(array $texts, ?string $model = null): EmbeddingResult;

    /**
     * Cheap connectivity check used by Settings → Test connection.
     */
    public function testConnection(): bool;

    public function usesByoKey(): bool;
}
