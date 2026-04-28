<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('chat', fn (Request $r) => [
            Limit::perMinute(10)->by(optional($r->user())->id ?: $r->ip()),
        ]);

        RateLimiter::for('upload', fn (Request $r) => [
            Limit::perMinute(5)->by(optional($r->user())->id ?: $r->ip()),
        ]);

        RateLimiter::for('summary', fn (Request $r) => [
            Limit::perMinute(6)->by(optional($r->user())->id ?: $r->ip()),
        ]);

        RateLimiter::for('draft', fn (Request $r) => [
            Limit::perMinute(20)->by(optional($r->user())->id ?: $r->ip()),
        ]);
    }
}
