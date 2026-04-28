<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(50);

        return ConversationResource::collection($conversations);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $conversation = Conversation::create([
            'title' => $data['title'] ?? 'New conversation',
            'provider' => $data['provider'] ?? $request->user()->default_provider ?? 'openai',
            'model' => $data['model'] ?? $request->user()->default_model ?? config('ai.default_chat_model'),
            'system_prompt' => $data['system_prompt'] ?? null,
        ]);

        if (! empty($data['document_ids'])) {
            $conversation->documents()->sync($data['document_ids']);
        }

        return (new ConversationResource($conversation->load('documents')))->response()->setStatusCode(201);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        $conversation->load(['documents', 'messages' => fn ($q) => $q->orderBy('created_at')]);
        return new ConversationResource($conversation);
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): ConversationResource
    {
        $data = $request->validated();
        if (array_key_exists('title', $data)) {
            $conversation->title = $data['title'];
        }
        if (array_key_exists('pinned', $data)) {
            $conversation->pinned_at = $data['pinned'] ? now() : null;
        }
        if (array_key_exists('provider', $data)) {
            $conversation->provider = $data['provider'];
        }
        if (array_key_exists('model', $data)) {
            $conversation->model = $data['model'];
        }
        $conversation->save();

        if (array_key_exists('document_ids', $data)) {
            $conversation->documents()->sync($data['document_ids']);
        }

        return new ConversationResource($conversation->fresh()->load('documents'));
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
