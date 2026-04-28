<?php

namespace App\Http\Middleware;

use App\Enums\PlanTier;
use App\Models\UsageRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTokenBudget
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $cap = $this->dailyCapFor($user);
        if ($cap === PHP_INT_MAX) {
            return $next($request);
        }

        $usedToday = UsageRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('used_byo_key', false)
            ->whereDate('created_at', now()->toDateString())
            ->sum('total_tokens');

        if ($usedToday >= $cap) {
            return response()->json([
                'message' => 'Daily token budget exhausted.',
                'used_today' => (int) $usedToday,
                'daily_cap' => $cap,
                'resets_at' => now()->addDay()->startOfDay()->toIso8601String(),
            ], 429);
        }

        $response = $next($request);

        // Use headers->set() so we work for both Laravel Response and Symfony StreamedResponse.
        $response->headers->set('X-Tokens-Used-Today', (string) (int) $usedToday);
        $response->headers->set('X-Tokens-Daily-Cap', (string) $cap);
        return $response;
    }

    protected function dailyCapFor($user): int
    {
        $tier = $user->plan_tier instanceof PlanTier ? $user->plan_tier : PlanTier::tryFrom((string) $user->plan_tier);
        return match ($tier) {
            PlanTier::Pro => (int) config('ai.daily_caps.pro', 500_000),
            PlanTier::Unlimited => PHP_INT_MAX,
            default => (int) config('ai.daily_caps.free', 50_000),
        };
    }
}
