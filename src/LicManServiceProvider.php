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
        ], 'config');
        // Migrations

        /*$this->publishes([
            __DIR__ . '/../migrations/' => database_path('migrations')
        ], 'migrations');*/
        $this->publishes([
            base_path('vendor/ingress-it-solutions/generator/migrations') => base_path('database/migrations'),
        ], 'migrations');

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
