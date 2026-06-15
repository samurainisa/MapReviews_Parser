<?php

namespace App\Providers;

use App\Services\Parser\YandexMapsParser;
use App\Services\Parser\YandexMapsParserInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Стратегия HTTP-first + browser fallback скрыта за интерфейсом.
        $this->app->bind(YandexMapsParserInterface::class, YandexMapsParser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
