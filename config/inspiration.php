<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Filesystem disk used for thumbnails and full-size downloads. Files are
    | stored under `inspiration/{user_id}/{source}/`.
    |
    */

    'disk' => env('INSPIRATION_DISK', 'local'),

    'subpath' => 'inspiration/{user_id}/{source}',

    /*
    |--------------------------------------------------------------------------
    | Downloads
    |--------------------------------------------------------------------------
    |
    | Per-user daily cap for on-demand full-size downloads.
    |
    */

    'max_downloads_per_day' => (int) env('INSPIRATION_MAX_DOWNLOADS_PER_DAY', 20),

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    |
    | Write-through cache lifetimes for search, explore and HTML scrapers.
    |
    */

    'ttl' => [
        'search' => 1800,
        'explore' => 3600,
        'scraper' => 21600,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (seconds) per tier
    |--------------------------------------------------------------------------
    |
    | Tier 1 = official APIs, Tier 2 = HTML scrapers, Tier 3 = opt-in sources.
    |
    */

    'timeouts' => [
        'tier1' => 5,
        'tier2' => 12,
        'tier3' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | App-level credentials
    |--------------------------------------------------------------------------
    |
    | DeviantArt uses OAuth2 client-credentials configured at app level.
    |
    */

    'deviantart' => [
        'client_id' => env('DEVIANTART_CLIENT_ID'),
        'client_secret' => env('DEVIANTART_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credential fields per source
    |--------------------------------------------------------------------------
    |
    | Canonical shape of the per-user credential bag. The settings form and
    | the update request validate `keys.{source}` against these field names
    | for every registered source whose capabilities require a key. Sources
    | missing from this map default to a single `key` field.
    |
    */

    'credential_fields' => [
        'flickr' => ['key'],
        'tumblr' => ['key'],
        'unsplash' => ['key'],
        'pexels' => ['key'],
        'pixabay' => ['key'],
        'discogs' => ['token'],
        'giphy' => ['key'],
        'europeana' => ['key'],
        'rijksmuseum' => ['key'],
        'wikiart' => ['key'],
        'pixiv' => ['refresh_token'],
        // Optional: SFW works without it; the key unlocks sketchy/NSFW when
        // the global maturity toggle is on (see WallhavenSource::purity()).
        'wallhaven' => ['key'],
        // Required since 2026: Gelbooru answers 401 without an API key.
        'gelbooru' => ['key'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier 3 sources
    |--------------------------------------------------------------------------
    |
    | Sources gated behind an explicit acknowledgement in the UI.
    |
    */

    'tier3' => ['pixiv', 'pinterest', 'cara', 'bandcamp', 'wikiart', 'newgrounds', 'mobbin'],

    /*
    |--------------------------------------------------------------------------
    | Session-auth capable sources
    |--------------------------------------------------------------------------
    |
    | Sources that accept an optional user session (cookie pasted from the
    | user's own browser, or a simulated login as fallback) to bypass public
    | bot-walls. Stored encrypted in the settings bag; never exposed in props.
    |
    */

    'auth_sources' => ['behance', 'newgrounds', 'pinterest', 'cara', 'mobbin', 'artstation'],

    /*
    |--------------------------------------------------------------------------
    | Home-wall subset
    |--------------------------------------------------------------------------
    |
    | The first render of the explore wall only fans out over these fast,
    | reliable sources; every other active source loads lazily when its chip
    | is clicked. Kept out of the year-2026 bot-walls on purpose.
    |
    */

    'home_sources' => ['aic', 'arena', 'openverse', 'wallhaven', 'zerochan', 'archdaily', 'cosmos', 'awwwards'],

    /*
    |--------------------------------------------------------------------------
    | Sources enabled by default
    |--------------------------------------------------------------------------
    |
    | Applied only when the user has no persisted settings row. Every key here
    | is a Tier 1 source with no user credential (DeviantArt's app-level OAuth
    | lives in `.env`) plus every Tier 2 HTML scraper. Tier 3 stays opt-in via
    | the acknowledgement flow. Once the user saves settings, their explicit
    | list wins — including an empty one (they turned everything off).
    |
    */

    'default_enabled_sources' => [
        'deviantart',
        'wallhaven',
        'openverse',
        'zerochan',
        'gelbooru',
        'arena',
        'aic',
        'designspiration',
        'trendlist',
        'posterspy',
        'brutalist',
        'archdaily',
        'godly',
        'darkmode',
        'dribbble',
        'awwwards',
        'cosmos',
    ],

];
