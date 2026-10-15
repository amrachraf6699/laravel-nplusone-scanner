<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner;

use Amrachraf6699\LaravelNPlusOneScanner\Console\ScanCommand;
use Illuminate\Support\ServiceProvider;

final class NPlusOneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nplusone.php', 'nplusone');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/nplusone.php' => config_path('nplusone.php')], 'nplusone-config');
        if ($this->app->runningInConsole()) {
            $this->commands([ScanCommand::class]);
        }
    }
}
