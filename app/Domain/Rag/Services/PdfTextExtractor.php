<?php

namespace App\Domain\Rag\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    /**
     * @return array{pages: array<int, string>, page_count: int}
     */
    public function extract(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("PDF not readable at $absolutePath");
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);
        $pages = $pdf->getPages();

        $out = [];
        foreach ($pages as $i => $page) {
            try {
                $text = $page->getText();
            } catch (\Throwable) {
                $text = '';
            }
            $out[$i + 1] = $text;
        }

        return ['pages' => $out, 'page_count' => count($out)];
    }
}
