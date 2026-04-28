<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanTier;
use App\Http\Controllers\Controller;
use App\Models\UsageRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $todayTokens = (int) UsageRecord::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('used_byo_key', false)
            ->sum('total_tokens');

        $cap = match ($user->plan_tier) {
            PlanTier::Pro => (int) config('ai.daily_caps.pro', 500_000),
            PlanTier::Unlimited => null,
            default => (int) config('ai.daily_caps.free', 50_000),
        };

        return response()->json([
            'used' => $todayTokens,
            'cap' => $cap,
            'remaining' => $cap ? max(0, $cap - $todayTokens) : null,
            'percentage' => $cap ? round(min(100, ($todayTokens / $cap) * 100), 1) : 0,
            'cost_usd' => (float) UsageRecord::query()
                ->whereDate('created_at', now()->toDateString())
                ->sum('cost_usd'),
        ]);
    }

    public function timeseries(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 14);
        $days = max(1, min(60, $days));

        $rows = UsageRecord::query()
            ->selectRaw("DATE(created_at) as day, action, SUM(total_tokens) as tokens, SUM(cost_usd) as cost")
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupByRaw('DATE(created_at), action')
            ->orderByRaw('DATE(created_at)')
            ->get();

        return response()->json(['series' => $rows]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        $rows = UsageRecord::query()
            ->selectRaw('provider, model, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens, SUM(cost_usd) as cost_usd, COUNT(*) as calls')
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('provider', 'model')
            ->orderByDesc('total_tokens')
            ->get();

        return response()->json(['breakdown' => $rows]);
    }
}
