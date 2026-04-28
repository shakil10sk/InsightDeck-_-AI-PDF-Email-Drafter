<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamWriter
{
    public static function sse(Closure $producer): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($producer) {
            // Disable buffering so the client sees tokens as they arrive.
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $writer = new self;
            $writer->event('open', ['ts' => now()->toIso8601String()]);
            $producer($writer);
            $writer->event('close', ['ts' => now()->toIso8601String()]);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }

    public function event(string $name, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "event: {$name}\n";
        echo "data: {$json}\n\n";
        if (function_exists('ob_flush')) @ob_flush();
        @flush();
    }
}
