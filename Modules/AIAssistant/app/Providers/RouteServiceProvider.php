<?php

namespace Modules\AIAssistant\app\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\AIAssistant\app\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebRoutes();
        $this->mapApiRoutes();
        $this->mapVendorRoutes();
        $this->mapAdminRoutes();
    }

    /**
     * Storefront chat widget: logged-in customer session or guest_id session,
     * exactly like the rest of the customer-facing checkout flow.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AIAssistant', '/routes/web.php'));
    }

    /**
     * Mobile app / third-party channel: stateless, Sanctum/Passport
     * customer-token guarded — mirrors RestAPI\v1\OrderController's
     * existing pattern (see architecture doc Part II §10).
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AIAssistant', '/routes/api.php'));
    }

    /**
     * Vendor panel: AI assistant configuration.
     */
    protected function mapVendorRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AIAssistant', '/routes/vendor.php'));
    }

    /**
     * Admin panel: platform-level AI provider/model/pricing configuration.
     */
    protected function mapAdminRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AIAssistant', '/routes/admin.php'));
    }
}
