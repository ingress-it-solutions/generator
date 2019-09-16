<?php 

namespace IngressITSolutions\Generator;

use Illuminate\Support\ServiceProvider;

class LicManServiceProvider extends ServiceProvider
{


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/lmconfig.php' => config_path('lmconfig.php'),
        ]);
        // Migrations
        if (method_exists($this, 'loadMigrationsFrom')) {
            $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        } else {
            $this->publishes([
                __DIR__ . '/../migrations/' => database_path('migrations')
            ], 'migrations');
        }
    }





    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lmconfig.php', 'lmconfig');
    }

}
