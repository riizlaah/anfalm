<?php

namespace App\Providers;

use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiProviderFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, static function (): AiProvider {
            return AiProviderFactory::make();
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
