<?php

namespace AziziZaidi\AllInOneCommand;

use Illuminate\Support\ServiceProvider;
use AziziZaidi\AllInOneCommand\Console\MakeFeatureCommand;

class AllInOneCommandServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFeatureCommand::class,
            ]);
        }
    }
}