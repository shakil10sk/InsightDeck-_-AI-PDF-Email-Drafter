<?php

namespace App\Http\Middleware;

use App\Domain\Ai\ProviderManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderConfigured
{
    public function __construct(protected ProviderManager $providers) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->providers->resolve($request->user());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No AI provider is configured. Add an API key in Settings or contact support.',
                'error' => $e->getMessage(),
            ], 503);
        }

        return $next($request);
    }
}
