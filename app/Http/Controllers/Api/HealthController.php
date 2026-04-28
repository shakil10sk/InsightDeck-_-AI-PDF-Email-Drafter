<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = false;
        try {
            DB::connection()->getPdo();
            $db = true;
        } catch (\Throwable) {
        }

        return response()->json([
            'ok' => true,
            'service' => 'insightdeck',
            'version' => config('app.version', '0.1.0'),
            'db' => $db,
            'time' => now()->toIso8601String(),
        ]);
    }
}
