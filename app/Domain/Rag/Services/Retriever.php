<?php

namespace App\Domain\Rag\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

class Retriever
{
    /**
     * Top-K nearest chunks across the supplied documents using pgvector cosine distance.
     *
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, int>    $documentIds
     * @return array<int, array{id:int, document_id:int, page_number:?int, content:string, similarity:float}>
     */
    public function topK(array $queryEmbedding, array $documentIds, int $k = 6): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return $this->topKFallback($queryEmbedding, $documentIds, $k);
        }

        $vectorLiteral = '['.implode(',', array_map(fn ($v) => (float) $v, $queryEmbedding)).']';

        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $sql = "SELECT id, document_id, page_number, content,
                       1 - (embedding <=> ?::vector) AS similarity
                FROM document_chunks
                WHERE document_id IN ($placeholders) AND embedding IS NOT NULL
                ORDER BY embedding <=> ?::vector ASC
                LIMIT ?";

        $bindings = array_merge([$vectorLiteral], $documentIds, [$vectorLiteral, $k]);
        $rows = DB::select($sql, $bindings);

        return array_map(fn ($r) => [
            'id' => (int) $r->id,
            'document_id' => (int) $r->document_id,
            'page_number' => $r->page_number !== null ? (int) $r->page_number : null,
            'content' => $r->content,
            'similarity' => (float) $r->similarity,
        ], $rows);
    }

    /**
     * In-PHP cosine similarity for non-Postgres drivers (e.g. SQLite during tests).
     */
    protected function topKFallback(array $queryEmbedding, array $documentIds, int $k): array
    {
        $rows = DocumentChunk::query()
            ->whereIn('document_id', $documentIds)
            ->whereNotNull('embedding')
            ->get(['id', 'document_id', 'page_number', 'content', 'embedding']);

        $qNorm = $this->norm($queryEmbedding);
        $scored = [];
        foreach ($rows as $row) {
            $vec = is_array($row->embedding) ? $row->embedding : json_decode($row->embedding, true);
            if (! is_array($vec)) continue;
            $sim = $this->cosine($queryEmbedding, $vec, $qNorm);
            $scored[] = [
                'id' => (int) $row->id,
                'document_id' => (int) $row->document_id,
                'page_number' => $row->page_number !== null ? (int) $row->page_number : null,
                'content' => $row->content,
                'similarity' => $sim,
            ];
        }

        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($scored, 0, $k);
    }

    protected function norm(array $v): float
    {
        $s = 0.0;
        foreach ($v as $x) $s += $x * $x;
        return sqrt($s);
    }

    protected function cosine(array $a, array $b, float $aNorm): float
    {
        $dot = 0.0;
        $bNormSq = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $bNormSq += $b[$i] * $b[$i];
        }
        $denom = $aNorm * sqrt($bNormSq);
        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
