<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => $user->only([
                'id', 'name', 'email', 'plan_tier', 'default_provider',
                'default_model', 'has_byo_openai_key', 'has_byo_anthropic_key',
                'email_verified_at', 'created_at',
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$request->user()->id,
        ]);
        $request->user()->fill($data)->save();
        return $this->show($request);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $user->delete();
        $request->session()->invalidate();
        return response()->json(['message' => 'Account deleted.']);
    }
}
