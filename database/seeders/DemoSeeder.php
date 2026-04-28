<?php

namespace Database\Seeders;

use App\Enums\PlanTier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('insightdeck.demo_email', 'demo@insightdeck.app')],
            [
                'name' => 'Demo User',
                'password' => Hash::make(config('insightdeck.demo_password', 'demo-password')),
                'plan_tier' => PlanTier::Pro->value,
                'email_verified_at' => now(),
                'default_provider' => 'openai',
                'default_model' => 'gpt-4o-mini',
            ]
        );

        $this->command?->info('Demo user ready: '.config('insightdeck.demo_email'));
    }
}
