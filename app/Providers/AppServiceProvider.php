<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);

        $this->app->bind(PaymentGatewayInterface::class, function (Application $app) {
            return $app->make(PaymentGatewayManager::class)->gateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
