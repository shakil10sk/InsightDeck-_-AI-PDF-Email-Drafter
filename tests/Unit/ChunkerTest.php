<?php

namespace Tests\Unit;

use App\Domain\Rag\Services\Chunker;
use Tests\TestCase;

class ChunkerTest extends TestCase
{
    public function test_it_chunks_text_within_size_limit(): void
    {
        $chunker = new Chunker(tokensPerChunk: 50, overlapTokens: 10, charsPerToken: 4);
        $sentence = str_repeat('Lorem ipsum dolor sit amet. ', 30);

        $chunks = $chunker->chunk([1 => $sentence]);

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $c) {
            $this->assertSame(1, $c['page_number']);
            $this->assertGreaterThan(0, $c['token_count']);
            $this->assertLessThanOrEqual(70 * 4, strlen($c['content'])); // chunk + overlap headroom
        }
    }

    public function test_it_skips_empty_pages(): void
    {
        $chunker = new Chunker();
        $chunks = $chunker->chunk([1 => '', 2 => '   ']);
        $this->assertSame([], $chunks);
    }

    public function test_it_preserves_page_numbers(): void
    {
        $chunker = new Chunker(tokensPerChunk: 25, overlapTokens: 5, charsPerToken: 4);
        $chunks = $chunker->chunk([
            3 => 'Sentence one. Sentence two. Sentence three.',
            4 => 'Page four content.',
        ]);
        $pages = array_unique(array_column($chunks, 'page_number'));
        sort($pages);
        $this->assertSame([3, 4], array_values($pages));
    }
}
