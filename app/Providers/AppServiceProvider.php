<?php

namespace App\Providers;

use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiScopeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound in `register()` (not `boot()`) because bindings must exist before
        // anything is resolved. Singletons keep the `ai_scopes` row cache in
        // `AiScopeResolver` and the health row cache in `AiHealthService` valid for
        // the whole request instead of diverging per resolution.
        $this->app->singleton(AiHealthService::class);
        $this->app->singleton(AiScopeResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
