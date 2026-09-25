<?php

namespace App\Providers;

use App\Ai\Providers\ReasoningOpenAiCompatibleProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

class AiChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Ai::extend('reasoning-compatible', fn ($app, array $config) => new ReasoningOpenAiCompatibleProvider($config, $app['events']));
    }
}
