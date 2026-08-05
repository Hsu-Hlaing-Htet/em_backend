<?php

namespace App\Providers;

use App\Contracts\DocumentPdfConverter;
use App\Services\ChromeDocumentPdfConverter;
use App\Services\FakeDocumentPdfConverter;
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
        //
    }
}
