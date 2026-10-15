<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $response = fn (Request $request, array $headers) => response()->json([
            'message' => __('messages.too_many_attempts'),
        ], 429, $headers);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:'.$request->ip())->response($response),
            Limit::perMinute(5)->by('login:'.$request->ip().':'.mb_strtolower((string) $request->input('email')))->response($response),
        ]);
        RateLimiter::for('mail', fn (Request $request) => [
            Limit::perMinute(10)->by('mail-ip:'.$request->ip())->response($response),
            Limit::perMinute(2)->by('mail:'.mb_strtolower((string) $request->input('email', $request->input('new_email', $request->user()?->email))))->response($response),
        ]);
    }
}
