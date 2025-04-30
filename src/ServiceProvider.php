<?php

namespace S3lp\PieceData;

use S3lp\PieceData\Console\SyncModelsExport;
use S3lp\PieceData\Observers\SyncObserver;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        //$this->mergeConfigFrom(__DIR__ . '/../config/sync_models.php', 'sync_models');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/sync_models.php' => config_path('sync_models.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncModelsExport::class]);
        }

//        if (config('sync_models.export_models')) {
//            $this->loadMigrationsFrom([__DIR__ . '/../database/migrations']);
//            $this->loadMigrationsFrom([__DIR__ . '/../database/migrations_test']);
//        }

        foreach (config('sync_models.export_models', []) as $models) {
            app($models)::observe(SyncObserver::class);
        }
    }
}
