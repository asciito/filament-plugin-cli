<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Prompts\Prompt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Prompt::fallbackWhen(! app()->isProduction());
    }
}
