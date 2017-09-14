<?php

namespace Kavanpancholi\Processlist;

use Illuminate\Support\ServiceProvider;

class ProcesslistServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__.'/routes.php';
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Kavanpancholi\Processlist\ProcesslistController');
    }
}
