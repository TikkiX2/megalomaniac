<?php

use App\Providers\AiChatServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\McpServiceProvider;

return [
    AiChatServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationServiceProvider::class,
    McpServiceProvider::class,
];
