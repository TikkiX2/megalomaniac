<?php

use App\Inspiration\InspirationServiceProvider;
use App\Providers\AiChatServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\McpServiceProvider;

return [
    AiChatServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    InspirationServiceProvider::class,
    IntegrationServiceProvider::class,
    McpServiceProvider::class,
];
