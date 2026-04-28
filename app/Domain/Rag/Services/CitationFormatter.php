<?php

namespace App\Domain\Rag\Services;

class CitationFormatter
{
    /**
     * Convert retrieved chunks into the inline-citation manifest the model is expected to reference.
     *
     * @param  array<int, array{id:int, document_id:int, page_number:?int, content:string}>  $chunks
     * @return array<int, array{n:int, chunk_id:int, document_id:int, page:?int, snippet:string}>
     */
    public function manifest(array $chunks): array
    {
        $out = [];
        foreach ($chunks as $i => $c) {
            $clean = trim(preg_replace('/\s+/', ' ', $c['content']));
            $snippet = mb_strlen($clean) > 120 ? mb_substr($clean, 0, 120).'…' : $clean;
            $out[] = [
                'n' => $i + 1,
                'chunk_id' => $c['id'],
                'document_id' => $c['document_id'],
                'page' => $c['page_number'] ?? null,
                'snippet' => $snippet,
            ];
        }
        return $out;
    }

    /**
     * Filter the manifest to only those numbers actually cited in the assistant's response.
     */
    public function inUseFrom(string $text, array $manifest): array
    {
        preg_match_all('/\[(\d+)\]/u', $text, $m);
        $used = array_unique(array_map('intval', $m[1] ?? []));
        return array_values(array_filter($manifest, fn ($c) => in_array($c['n'], $used, true)));
    }
}
