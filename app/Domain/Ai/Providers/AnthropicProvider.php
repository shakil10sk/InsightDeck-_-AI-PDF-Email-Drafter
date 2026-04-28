<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Dto\ChatChunk;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\Dto\EmbeddingResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AiProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $apiVersion = '2023-06-01',
        protected bool $usesByoKey = false,
        // Anthropic does not provide an embeddings endpoint; we delegate to OpenAI for vectors.
        protected ?AiProvider $embeddingsFallback = null,
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function usesByoKey(): bool
    {
        return $this->usesByoKey;
    }

    public function streamChat(ChatRequest $request): iterable
    {
        $payload = $this->buildPayload($request, stream: true);

        $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'content-type' => 'application/json',
                'accept' => 'text/event-stream',
            ])
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post("{$this->baseUrl}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic error: HTTP '.$response->status().' — '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $promptTokens = null;
        $completionTokens = null;

        while (! $body->eof()) {
            $buffer .= $body->read(2048);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if ($line === '' || ! str_starts_with($line, 'data:')) continue;

                $data = trim(substr($line, 5));
                $json = json_decode($data, true);
                if (! is_array($json)) continue;

                $type = $json['type'] ?? null;

                if ($type === 'message_start') {
                    $promptTokens = $json['message']['usage']['input_tokens'] ?? $promptTokens;
                } elseif ($type === 'content_block_delta') {
                    $delta = $json['delta']['text'] ?? null;
                    if ($delta !== null && $delta !== '') {
                        yield new ChatChunk($delta, false);
                    }
                } elseif ($type === 'message_delta') {
                    $completionTokens = $json['usage']['output_tokens'] ?? $completionTokens;
                } elseif ($type === 'message_stop') {
                    yield new ChatChunk('', true, $promptTokens, $completionTokens);
                    return;
                }
            }
        }

        yield new ChatChunk('', true, $promptTokens, $completionTokens);
    }

    public function chat(ChatRequest $request): ChatChunk
    {
        $payload = $this->buildPayload($request, stream: false);

        $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'content-type' => 'application/json',
            ])
            ->timeout(60)
            ->post("{$this->baseUrl}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic error: HTTP '.$response->status().' — '.$response->body());
        }

        $json = $response->json();
        $text = '';
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return new ChatChunk(
            delta: $text,
            done: true,
            promptTokens: $json['usage']['input_tokens'] ?? null,
            completionTokens: $json['usage']['output_tokens'] ?? null,
        );
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResult
    {
        if (! $this->embeddingsFallback) {
            throw new RuntimeException('Anthropic does not provide embeddings; configure OpenAI for embedding fallback.');
        }
        return $this->embeddingsFallback->embed($texts, $model);
    }

    public function testConnection(): bool
    {
        // Anthropic exposes /messages only — issue a tiny request to validate the key.
        $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'content-type' => 'application/json',
            ])
            ->timeout(15)
            ->post("{$this->baseUrl}/messages", [
                'model' => config('ai.providers.anthropic.chat_models.0', 'claude-haiku-4-5'),
                'max_tokens' => 8,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]);
        return $response->successful();
    }

    protected function buildPayload(ChatRequest $request, bool $stream): array
    {
        $messages = [];
        foreach ($request->messages as $m) {
            $messages[] = $m->toArray();
        }

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];
        if ($request->system !== null && $request->system !== '') {
            $payload['system'] = $request->system;
        }
        if ($stream) {
            $payload['stream'] = true;
        }
        return $payload;
    }
}
