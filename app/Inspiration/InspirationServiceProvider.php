<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Contracts\Source;
use App\Inspiration\Sources\Api\AreNaSource;
use App\Inspiration\Sources\Api\ArtInstituteChicagoSource;
use App\Inspiration\Sources\Api\ArtStationSource;
use App\Inspiration\Sources\Api\BandcampSource;
use App\Inspiration\Sources\Api\DeviantArtSource;
use App\Inspiration\Sources\Api\DiscogsSource;
use App\Inspiration\Sources\Api\EuropeanaSource;
use App\Inspiration\Sources\Api\FlickrSource;
use App\Inspiration\Sources\Api\GelbooruSource;
use App\Inspiration\Sources\Api\GiphySource;
use App\Inspiration\Sources\Api\MetMuseumSource;
use App\Inspiration\Sources\Api\OpenverseSource;
use App\Inspiration\Sources\Api\PexelsSource;
use App\Inspiration\Sources\Api\PixabaySource;
use App\Inspiration\Sources\Api\PixivSource;
use App\Inspiration\Sources\Api\RijksmuseumSource;
use App\Inspiration\Sources\Api\TumblrSource;
use App\Inspiration\Sources\Api\UnsplashSource;
use App\Inspiration\Sources\Api\WallhavenSource;
use App\Inspiration\Sources\Api\WikiArtSource;
use App\Inspiration\Sources\Api\ZerochanSource;
use App\Inspiration\Sources\Scrape\AwwwardsSource;
use App\Inspiration\Sources\Scrape\BehanceSource;
use App\Inspiration\Sources\Scrape\BrutalistSource;
use App\Inspiration\Sources\Scrape\DarkModeDesignSource;
use App\Inspiration\Sources\Scrape\DesignspirationSource;
use App\Inspiration\Sources\Scrape\DribbbleSource;
use App\Inspiration\Sources\Scrape\GodlySource;
use App\Inspiration\Sources\Scrape\LapaNinjaSource;
use App\Inspiration\Sources\Scrape\NewgroundsSource;
use App\Inspiration\Sources\Scrape\PosterSpySource;
use App\Inspiration\Sources\Scrape\SaveeSource;
use App\Inspiration\Sources\Scrape\TrendListSource;
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
        ZerochanSource::class,
        GelbooruSource::class,
        AreNaSource::class,
        MetMuseumSource::class,
        ArtInstituteChicagoSource::class,
        FlickrSource::class,
        TumblrSource::class,
        UnsplashSource::class,
        PexelsSource::class,
        PixabaySource::class,
        DiscogsSource::class,
        GiphySource::class,
        EuropeanaSource::class,
        RijksmuseumSource::class,
        WikiArtSource::class,
        DesignspirationSource::class,
        SaveeSource::class,
        TrendListSource::class,
        PosterSpySource::class,
        LapaNinjaSource::class,
        GodlySource::class,
        DarkModeDesignSource::class,
        BrutalistSource::class,
        BehanceSource::class,
        DribbbleSource::class,
        AwwwardsSource::class,
        NewgroundsSource::class,
        PixivSource::class,
        BandcampSource::class,
    ];

    public function register(): void
    {
        $this->app->singleton(SourceManager::class);

        $this->app->tag(self::SOURCES, 'inspiration.sources');
    }
}
