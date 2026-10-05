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

];
