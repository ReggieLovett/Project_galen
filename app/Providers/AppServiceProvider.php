<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Assistant\PharmacyAssistant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No API key: the assistant runs on its built-in rules and template.
        $this->app->singleton(PharmacyAssistant::class, fn () => new PharmacyAssistant(
            filled($key = config('services.anthropic.key'))
                ? new Client(apiKey: $key, requestOptions: [
                    'timeout' => config('kiosk.assistant.timeout'),
                    'maxRetries' => 1,
                ])
                : null,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // An N+1 query throws during development instead of slowing the kiosk.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureRateLimits();
    }

    /**
     * Everyone at one kiosk shares its IP address, so each limit is keyed
     * by what identifies the person, and going over it shows a message on
     * the kiosk screen instead of an error page.
     */
    private function configureRateLimits(): void
    {
        RateLimiter::for('staff-login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()))
            ->response(fn (Request $request, array $headers) => back()
                ->withErrors(['email' => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60])])
                ->onlyInput('email')));

        // Keyed by device on purpose: this limit stops someone at the kiosk
        // guessing card numbers, whichever numbers they try.
        RateLimiter::for('member-sign-in', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip())
            ->response(fn () => back()
                ->withErrors(['member_number' => 'Too many tries. Please wait a minute or ask at the counter.'])));

        // Each assistant question can cost an API call.
        RateLimiter::for('assistant', function (Request $request) {
            $wait = fn () => back()
                ->withErrors(['question' => 'The assistant needs a short break. Please try again in a minute.'])
                ->with('panel', 'assistant');

            return [
                Limit::perMinute(10)->by('session:'.$request->session()->getId())->response($wait),
                Limit::perMinute(30)->by('device:'.$request->ip())->response($wait),
            ];
        });
    }
}
