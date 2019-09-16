<?php 

namespace IngressITSolutions\Provider;

use Illuminate\Support\ServiceProvider;

class LicManServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lmconfig.php', 'lmconfig');
    }
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Config
        $this->publishes([
            __DIR__.'/../config/lmconfig.php' => base_path('config/lmconfig.php'),
        ]);
        // Migrations
        if (method_exists($this, 'loadMigrationsFrom')) {
            $this->loadMigrationsFrom(__DIR__.'/../migrations');
        } else {
            $this->publishes([
                __DIR__.'/../migrations/' => database_path('migrations')
            ], 'migrations');
        }
        
    }
}