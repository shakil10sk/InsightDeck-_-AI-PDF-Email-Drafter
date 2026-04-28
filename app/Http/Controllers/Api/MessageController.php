<?php

namespace App\Http\Controllers\Api;

use App\Domain\Rag\Services\ChatStreamer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\StreamWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function __construct(protected ChatStreamer $streamer) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): StreamedResponse
    {
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->string('content')->toString(),
            'status' => 'complete',
        ]);

        return StreamWriter::sse(function (StreamWriter $w) use ($conversation, $userMessage, $request) {
            $w->event('user_message', ['id' => $userMessage->id, 'created_at' => $userMessage->created_at?->toIso8601String()]);

            $this->streamer->stream(
                conversation: $conversation->fresh(),
                userMessage: $userMessage,
                requestUser: $request->user(),
                onCitations: fn (array $citations) => $w->event('citations', ['citations' => $citations]),
                onChunk: fn (string $delta) => $w->event('delta', ['text' => $delta]),
                onDone: fn (Message $assistant) => $w->event('done', [
                    'message' => [
                        'id' => $assistant->id,
                        'role' => $assistant->role,
                        'content' => $assistant->content,
                        'citations' => $assistant->citations ?? [],
                        'prompt_tokens' => $assistant->prompt_tokens,
                        'completion_tokens' => $assistant->completion_tokens,
                        'cost_usd' => (float) $assistant->cost_usd,
                        'model' => $assistant->model,
                    ],
                ]),
                onError: fn (\Throwable $e) => $w->event('error', ['message' => $e->getMessage()]),
            );
        });
    }

    public function regenerate(Conversation $conversation, Message $message): StreamedResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->role === 'assistant', 422, 'Only assistant messages can be regenerated.');

        // Find the user message immediately preceding this assistant message.
        $userMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('created_at', '<', $message->created_at)
            ->latest('created_at')
            ->first();

        abort_unless($userMessage, 422, 'No user message to regenerate from.');

        $message->delete();

        return StreamWriter::sse(function (StreamWriter $w) use ($conversation, $userMessage) {
            $this->streamer->stream(
                conversation: $conversation->fresh(),
                userMessage: $userMessage,
                requestUser: request()->user(),
                onCitations: fn (array $citations) => $w->event('citations', ['citations' => $citations]),
                onChunk: fn (string $delta) => $w->event('delta', ['text' => $delta]),
                onDone: fn (Message $assistant) => $w->event('done', ['message' => $assistant->toArray()]),
                onError: fn (\Throwable $e) => $w->event('error', ['message' => $e->getMessage()]),
            );
        });
    }
}
