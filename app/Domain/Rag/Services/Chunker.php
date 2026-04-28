<?php

namespace App\Domain\Rag\Services;

class Chunker
{
    public function __construct(
        protected int $tokensPerChunk = 500,
        protected int $overlapTokens = 50,
        protected int $charsPerToken = 4,
    ) {}

    /**
     * @param  array<int, string>  $pages  page_number => text
     * @return array<int, array{page_number:int, content:string, token_count:int}>
     */
    public function chunk(array $pages): array
    {
        $chunkChars = $this->tokensPerChunk * $this->charsPerToken;
        $overlapChars = $this->overlapTokens * $this->charsPerToken;
        $chunks = [];

        foreach ($pages as $page => $text) {
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text === '') continue;

            $sentences = $this->splitSentences($text);
            $current = '';

            foreach ($sentences as $sentence) {
                if (mb_strlen($current) + mb_strlen($sentence) + 1 <= $chunkChars) {
                    $current .= ($current === '' ? '' : ' ').$sentence;
                    continue;
                }

                if ($current !== '') {
                    $chunks[] = $this->makeChunk($current, $page);
                }

                // Start the next chunk with a tail of the previous chunk for context overlap.
                $tail = mb_substr($current, max(0, mb_strlen($current) - $overlapChars));
                $current = $tail !== '' ? $tail.' '.$sentence : $sentence;
            }

            if ($current !== '') {
                $chunks[] = $this->makeChunk($current, $page);
            }
        }

        return array_values($chunks);
    }

    /**
     * @return array<int, string>
     */
    protected function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $text) ?: [$text];
        return array_values(array_filter(array_map('trim', $parts)));
    }

    protected function makeChunk(string $content, int $page): array
    {
        return [
            'page_number' => $page,
            'content' => $content,
            'token_count' => max(1, (int) ceil(mb_strlen($content) / $this->charsPerToken)),
        ];
    }
}
