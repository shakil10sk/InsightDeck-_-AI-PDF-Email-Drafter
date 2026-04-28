<?php

namespace Tests\Unit;

use App\Domain\Rag\Services\CitationFormatter;
use Tests\TestCase;

class CitationFormatterTest extends TestCase
{
    public function test_manifest_assigns_sequential_numbers(): void
    {
        $formatter = new CitationFormatter();
        $chunks = [
            ['id' => 11, 'document_id' => 1, 'page_number' => 3, 'content' => 'Foo bar baz quux.'],
            ['id' => 22, 'document_id' => 1, 'page_number' => 7, 'content' => 'Hello world.'],
        ];
        $manifest = $formatter->manifest($chunks);
        $this->assertSame(1, $manifest[0]['n']);
        $this->assertSame(11, $manifest[0]['chunk_id']);
        $this->assertSame(2, $manifest[1]['n']);
        $this->assertSame(22, $manifest[1]['chunk_id']);
    }

    public function test_in_use_filters_by_referenced_numbers(): void
    {
        $formatter = new CitationFormatter();
        $manifest = [
            ['n' => 1, 'chunk_id' => 11, 'document_id' => 1, 'page' => 3, 'snippet' => 'foo'],
            ['n' => 2, 'chunk_id' => 22, 'document_id' => 1, 'page' => 7, 'snippet' => 'bar'],
            ['n' => 3, 'chunk_id' => 33, 'document_id' => 1, 'page' => 9, 'snippet' => 'baz'],
        ];

        $used = $formatter->inUseFrom('See [1] and also [3] here.', $manifest);

        $this->assertCount(2, $used);
        $this->assertSame([1, 3], array_column($used, 'n'));
    }
}
