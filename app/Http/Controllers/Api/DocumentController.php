<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            Document::query()->latest()->paginate(20)
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = config('filesystems.default');
        $userId = $request->user()->id;
        $path = "documents/{$userId}/".\Illuminate\Support\Str::uuid()->toString().'.pdf';

        Storage::disk($disk)->put($path, $file->getContent(), 'private');

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Untitled document';
        }

        $document = Document::create([
            'title' => $title,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'status' => 'pending',
        ]);

        ProcessDocument::dispatch($document->id);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('summaries'));
    }

    public function destroy(Document $document): JsonResponse
    {
        if ($document->storage_path) {
            Storage::disk($document->storage_disk ?? config('filesystems.default'))->delete($document->storage_path);
        }
        $document->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function file(Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->storage_disk ?? config('filesystems.default'));
        abort_unless($disk->exists($document->storage_path), 404);

        return $disk->response($document->storage_path, $document->original_filename, [
            'Content-Type' => $document->mime_type ?: 'application/pdf',
        ]);
    }
}
