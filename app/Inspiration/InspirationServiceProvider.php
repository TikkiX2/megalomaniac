<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Contracts\Source;
use App\Inspiration\Sources\Api\ArtStationSource;
use App\Inspiration\Sources\Api\DeviantArtSource;
use App\Inspiration\Sources\Api\OpenverseSource;
use App\Inspiration\Sources\Api\WallhavenSource;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the inspiration source registry.
 *
 * Every adapter wave appends its concrete classes to SOURCES; SourceManager
 * resolves them from the `inspiration.sources` container tag at runtime so
 * tests can swap in fakes without touching production bindings.
 */
class InspirationServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string<Source>>
     */
    public const SOURCES = [
        DeviantArtSource::class,
        ArtStationSource::class,
        WallhavenSource::class,
        OpenverseSource::class,
    ];

    public function register(): void
    {
        $this->app->singleton(SourceManager::class);

        $this->app->tag(self::SOURCES, 'inspiration.sources');
    }
}
