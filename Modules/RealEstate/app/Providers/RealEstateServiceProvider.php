<?php

namespace Modules\RealEstate\app\Providers;

use Illuminate\Support\ServiceProvider;

class RealEstateServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'RealEstate';

    protected string $moduleNameLower = 'realestate';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), $this->moduleNameLower);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
    }
}
