<?php

namespace App\Providers;

use App\Integrations\ConnectorRegistry;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class, function () {
            return new ConnectorRegistry(config('integrations.connectors', []));
        });
    }
}
