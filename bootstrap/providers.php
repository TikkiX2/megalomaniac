<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\McpServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationServiceProvider::class,
    McpServiceProvider::class,
];
