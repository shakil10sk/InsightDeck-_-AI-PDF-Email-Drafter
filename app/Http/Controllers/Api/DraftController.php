<?php

namespace App\Http\Controllers\Api;

use App\Domain\Drafting\Services\DraftStreamer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\IterateDraftRequest;
use App\Http\Requests\Draft\StoreDraftRequest;
use App\Http\Resources\DraftResource;
use App\Models\Draft;
use App\Support\StreamWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DraftController extends Controller
{
    public function __construct(protected DraftStreamer $streamer) {}

    public function index(): AnonymousResourceCollection
    {
        return DraftResource::collection(Draft::query()->latest()->paginate(30));
    }

    public function show(Draft $draft): DraftResource
    {
        return new DraftResource($draft);
    }

    public function destroy(Draft $draft): JsonResponse
    {
        $draft->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function store(StoreDraftRequest $request): StreamedResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $draft = Draft::create([
            'goal' => $data['goal'],
            'recipient' => $data['recipient'] ?? null,
            'tone' => $data['tone'] ?? 'friendly',
            'length' => $data['length'] ?? 'medium',
            'context' => $data['context'] ?? null,
            'output' => '',
            'provider' => $data['provider'] ?? $user->default_provider ?? 'openai',
            'model' => $data['model'] ?? $user->default_model ?? config('ai.default_chat_model'),
        ]);

        return StreamWriter::sse(function (StreamWriter $w) use ($draft, $user) {
            $w->event('start', ['id' => $draft->id]);
            $this->streamer->stream(
                draft: $draft,
                user: $user,
                onChunk: fn (string $delta) => $w->event('delta', ['text' => $delta]),
                onDone: fn (Draft $final) => $w->event('done', (new DraftResource($final->fresh()))->toArray(request())),
                onError: fn (\Throwable $e) => $w->event('error', ['message' => $e->getMessage()]),
            );
        });
    }

    public function iterate(IterateDraftRequest $request, Draft $draft): StreamedResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $child = Draft::create([
            'parent_draft_id' => $draft->id,
            'goal' => $data['instruction'],
            'recipient' => $draft->recipient,
            'tone' => $data['tone'] ?? $draft->tone,
            'length' => $data['length'] ?? $draft->length,
            'context' => $draft->output,
            'output' => '',
            'provider' => $draft->provider,
            'model' => $draft->model,
        ]);

        return StreamWriter::sse(function (StreamWriter $w) use ($child, $user) {
            $w->event('start', ['id' => $child->id, 'parent_id' => $child->parent_draft_id]);
            $this->streamer->stream(
                draft: $child,
                user: $user,
                onChunk: fn (string $delta) => $w->event('delta', ['text' => $delta]),
                onDone: fn (Draft $final) => $w->event('done', (new DraftResource($final->fresh()))->toArray(request())),
                onError: fn (\Throwable $e) => $w->event('error', ['message' => $e->getMessage()]),
            );
        });
    }
}
