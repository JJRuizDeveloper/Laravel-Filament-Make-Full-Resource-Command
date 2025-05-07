<?php

namespace SixtyFourSoftware\MakeFullResource;

use Illuminate\Support\ServiceProvider;

class MakeFullResourceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // Registrar el comando en el Artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MakeFullResource::class,
            ]);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // Aquí puedes registrar servicios si es necesario
    }
}
