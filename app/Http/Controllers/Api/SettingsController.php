<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ai\ProviderManager;
use App\Enums\AiProviderName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SettingsController extends Controller
{
    public function __construct(protected ProviderManager $providers) {}

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('default_provider', $data)) {
            $user->default_provider = $data['default_provider'];
        }
        if (array_key_exists('default_model', $data)) {
            $user->default_model = $data['default_model'];
        }
        if (array_key_exists('byo_openai_key', $data)) {
            $user->byo_openai_key_encrypted = $data['byo_openai_key']
                ? Crypt::encryptString($data['byo_openai_key'])
                : null;
        }
        if (array_key_exists('byo_anthropic_key', $data)) {
            $user->byo_anthropic_key_encrypted = $data['byo_anthropic_key']
                ? Crypt::encryptString($data['byo_anthropic_key'])
                : null;
        }
        $user->save();

        return response()->json([
            'user' => $user->fresh()->only([
                'id', 'name', 'email', 'plan_tier', 'default_provider',
                'default_model', 'has_byo_openai_key', 'has_byo_anthropic_key',
            ]),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:openai,anthropic',
        ]);

        try {
            $provider = $this->providers->forName(
                AiProviderName::from($request->string('provider')),
                $request->user(),
            );
            $ok = $provider->testConnection();
            return response()->json(['ok' => $ok]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
