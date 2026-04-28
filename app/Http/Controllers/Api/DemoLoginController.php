<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $email = config('insightdeck.demo_email', 'demo@insightdeck.app');
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Demo account not seeded. Run `php artisan db:seed --class=DemoSeeder`.',
            ], 503);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'plan_tier', 'created_at']),
            'message' => 'Welcome to the InsightDeck demo.',
        ]);
    }
}
