<?php

namespace App\Jobs;

use App\Domain\Ai\ProviderManager;
use App\Domain\Rag\Services\Chunker;
use App\Domain\Rag\Services\PdfTextExtractor;
use App\Domain\Rag\Services\UsageLogger;
use App\Enums\DocumentStatus;
use App\Enums\UsageAction;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $documentId) {}

    public function handle(
        PdfTextExtractor $extractor,
        Chunker $chunker,
        ProviderManager $providers,
        UsageLogger $usage,
    ): void {
        $document = Document::query()->withoutGlobalScopes()->find($this->documentId);
        if (! $document) return;

        $document->forceFill(['status' => DocumentStatus::Processing])->save();

        try {
            $disk = Storage::disk($document->storage_disk ?? config('filesystems.default'));
            if (! $disk->exists($document->storage_path)) {
                $document->markFailed('File missing from storage.');
                return;
            }

            // smalot/pdfparser only reads from the local filesystem, so copy to a temp file.
            $tmp = tempnam(sys_get_temp_dir(), 'idk_').'.pdf';
            file_put_contents($tmp, $disk->get($document->storage_path));

            try {
                $extracted = $extractor->extract($tmp);
            } finally {
                @unlink($tmp);
            }

            if ($extracted['page_count'] === 0) {
                $document->markFailed('No pages extracted from PDF.');
                return;
            }

            $chunks = $chunker->chunk($extracted['pages']);
            if (empty($chunks)) {
                $document->markFailed('No textual content found. The PDF may be a scanned image.');
                return;
            }

            $embedder = $providers->embeddingProvider($document->user);
            $batchSize = (int) config('ai.embedding.batch_size', 100);
            $totalEmbedTokens = 0;
            $embeddingModel = config('ai.providers.openai.embedding_model', 'text-embedding-3-small');

            foreach (array_chunk($chunks, $batchSize) as $batchOffset => $batch) {
                $texts = array_map(fn ($c) => $c['content'], $batch);
                $result = $embedder->embed($texts);
                $totalEmbedTokens += $result->tokensUsed;

                foreach ($batch as $i => $chunk) {
                    $vec = $result->embeddings[$i] ?? null;
                    $row = [
                        'document_id' => $document->id,
                        'chunk_index' => ($batchOffset * $batchSize) + $i,
                        'page_number' => $chunk['page_number'],
                        'content' => $chunk['content'],
                        'token_count' => $chunk['token_count'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (DB::connection()->getDriverName() === 'pgsql' && $vec !== null) {
                        $id = DB::table('document_chunks')->insertGetId($row);
                        $literal = '['.implode(',', array_map(fn ($v) => (float) $v, $vec)).']';
                        DB::statement("UPDATE document_chunks SET embedding = ?::vector WHERE id = ?", [$literal, $id]);
                    } else {
                        $row['embedding'] = $vec !== null ? json_encode($vec) : null;
                        DB::table('document_chunks')->insert($row);
                    }
                }
            }

            $usage->record(
                userId: $document->user_id,
                action: UsageAction::Embed->value,
                provider: $embedder->name(),
                model: $embeddingModel,
                promptTokens: $totalEmbedTokens,
                completionTokens: 0,
                usedByoKey: $embedder->usesByoKey(),
                relatedType: Document::class,
                relatedId: $document->id,
            );

            $document->forceFill([
                'page_count' => $extracted['page_count'],
                'status' => DocumentStatus::Ready,
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $document->markFailed('Processing failed: '.$e->getMessage());
        }
    }
}
