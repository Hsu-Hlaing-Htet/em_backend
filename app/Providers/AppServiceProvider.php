<?php

namespace App\Providers;

use App\Contracts\DocumentPdfConverter;
use App\Services\ChromeDocumentPdfConverter;
use App\Services\FakeDocumentPdfConverter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentPdfConverter::class, function () {
            if ($this->app->environment('testing')) {
                return new FakeDocumentPdfConverter;
            }

            return new ChromeDocumentPdfConverter;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            $appUrl = config('app.url');
            if (is_string($appUrl) && $appUrl !== '') {
                URL::forceRootUrl(rtrim($appUrl, '/'));
            }
        }

        RateLimiter::for('ai-public', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
