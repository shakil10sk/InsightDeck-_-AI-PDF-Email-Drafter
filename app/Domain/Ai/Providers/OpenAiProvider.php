<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Dto\ChatChunk;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\Dto\EmbeddingResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected bool $usesByoKey = false,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function usesByoKey(): bool
    {
        return $this->usesByoKey;
    }

    public function streamChat(ChatRequest $request): iterable
    {
        $payload = $this->buildChatPayload($request, stream: true);

        $response = Http::withToken($this->apiKey)
            ->withOptions(['stream' => true])
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI error: HTTP '.$response->status().' — '.$response->body());
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
                if ($data === '[DONE]') {
                    yield new ChatChunk('', true, $promptTokens, $completionTokens);
                    return;
                }

                $json = json_decode($data, true);
                if (! is_array($json)) continue;

                if (isset($json['usage'])) {
                    $promptTokens = $json['usage']['prompt_tokens'] ?? $promptTokens;
                    $completionTokens = $json['usage']['completion_tokens'] ?? $completionTokens;
                }

                $delta = $json['choices'][0]['delta']['content'] ?? null;
                if ($delta !== null && $delta !== '') {
                    yield new ChatChunk($delta, false);
                }
            }
        }

        yield new ChatChunk('', true, $promptTokens, $completionTokens);
    }

    public function chat(ChatRequest $request): ChatChunk
    {
        $payload = $this->buildChatPayload($request, stream: false);

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI error: HTTP '.$response->status().' — '.$response->body());
        }

        $json = $response->json();
        return new ChatChunk(
            delta: $json['choices'][0]['message']['content'] ?? '',
            done: true,
            promptTokens: $json['usage']['prompt_tokens'] ?? null,
            completionTokens: $json['usage']['completion_tokens'] ?? null,
        );
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResult
    {
        $model ??= config('ai.providers.openai.embedding_model', 'text-embedding-3-small');

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/embeddings", [
                'model' => $model,
                'input' => $texts,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI embeddings error: HTTP '.$response->status().' — '.$response->body());
        }

        $json = $response->json();
        $vectors = array_map(fn ($d) => $d['embedding'], $json['data'] ?? []);

        return new EmbeddingResult(
            embeddings: $vectors,
            tokensUsed: $json['usage']['total_tokens'] ?? 0,
            model: $model,
        );
    }

    public function testConnection(): bool
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/models");
        return $response->successful();
    }

    protected function buildChatPayload(ChatRequest $request, bool $stream): array
    {
        $messages = [];
        if ($request->system !== null && $request->system !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }
        foreach ($request->messages as $m) {
            $messages[] = $m->toArray();
        }

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];
        if ($stream) {
            $payload['stream'] = true;
            $payload['stream_options'] = ['include_usage' => true];
        }
        return $payload;
    }
}
