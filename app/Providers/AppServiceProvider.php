<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        RateLimiter::for('script-generation', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(2)
                    ->by('generation-minute:'.$userId),

                Limit::perHour(10)
                    ->by('generation-hour:'.$userId),
            ];
        });

        RateLimiter::for('script-revision', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(2)
                    ->by('revision-minute:'.$userId),

                Limit::perHour(10)
                    ->by('revision-hour:'.$userId),
            ];
        });
    }
}
