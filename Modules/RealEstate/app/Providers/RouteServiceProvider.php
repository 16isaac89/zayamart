<?php

namespace Modules\RealEstate\app\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\RealEstate\app\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebRoutes();
        $this->mapVendorRoutes();
        $this->mapAdminRoutes();
    }

    /**
     * Public listing search/detail pages + inquiry form — same guest/customer
     * session handling as the rest of the storefront.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('RealEstate', '/routes/web.php'));
    }

    /**
     * Vendor panel: broker profile + listing/inquiry management.
     */
    protected function mapVendorRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('RealEstate', '/routes/vendor.php'));
    }

    /**
     * Admin panel: listing moderation.
     */
    protected function mapAdminRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('RealEstate', '/routes/admin.php'));
    }
}
