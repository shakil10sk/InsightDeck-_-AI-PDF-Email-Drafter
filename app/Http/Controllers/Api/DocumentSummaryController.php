<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SummaryResource;
use App\Models\Document;
use App\Support\StreamWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentSummaryController extends Controller
{
    public function show(Request $request, Document $document): JsonResponse
    {
        $length = $request->input('length', 'medium');
        $summary = $document->summaries()->where('length', $length)->latest()->first();

        return response()->json([
            'summary' => $summary ? (new SummaryResource($summary))->toArray($request) : null,
        ]);
    }

    public function store(Request $request, Document $document): StreamedResponse
    {
        $request->validate([
            'length' => 'sometimes|in:short,medium,long',
            'provider' => 'sometimes|in:openai,anthropic',
            'model' => 'sometimes|string|max:80',
        ]);

        $length = $request->input('length', 'medium');

        return StreamWriter::sse(function (StreamWriter $w) use ($document, $length, $request) {
            $service = app(\App\Domain\Rag\Services\Summarizer::class);
            $service->stream(
                document: $document,
                length: $length,
                user: $request->user(),
                provider: $request->input('provider'),
                model: $request->input('model'),
                onChunk: fn (string $chunk) => $w->event('delta', ['text' => $chunk]),
                onDone: fn (array $payload) => $w->event('done', $payload),
            );
        });
    }
}
