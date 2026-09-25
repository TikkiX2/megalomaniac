<?php

use App\Integrations\Connectors\Arr\JellyseerrConnector;
use App\Integrations\Connectors\Arr\ProwlarrConnector;
use App\Integrations\Connectors\Arr\QbittorrentConnector;
use App\Integrations\Connectors\Arr\RadarrConnector;
use App\Integrations\Connectors\Arr\SonarrConnector;
use App\Integrations\Connectors\Docker\DockerConnector;
use App\Integrations\Connectors\Github\GithubConnector;
use App\Integrations\Connectors\Google\GoogleConnector;
use App\Integrations\Connectors\HomeAssistant\HomeAssistantConnector;
use App\Integrations\Connectors\Jellyfin\JellyfinConnector;
use App\Integrations\Connectors\Listenbrainz\ListenbrainzConnector;
use App\Integrations\Connectors\Notion\NotionConnector;
use App\Integrations\Connectors\Proxmox\ProxmoxConnector;
use App\Integrations\Connectors\Reddit\RedditConnector;
use App\Integrations\Connectors\Rss\RssConnector;
use App\Integrations\Connectors\Telegram\TelegramConnector;
use App\Integrations\Connectors\Youtube\YoutubeConnector;
use App\Storage\Connectors\DropboxStorageConnector;
use App\Storage\Connectors\FtpStorageConnector;
use App\Storage\Connectors\GoogleDriveStorageConnector;
use App\Storage\Connectors\LocalStorageConnector;
use App\Storage\Connectors\S3StorageConnector;
use App\Storage\Connectors\SftpStorageConnector;
use App\Storage\Connectors\WebdavStorageConnector;

return [

    /*
    |--------------------------------------------------------------------------
    | Connector Registry
    |--------------------------------------------------------------------------
    |
    | Every connector class registered here becomes a connection kind that
    | users can create from Settings -> Connections. Each class must
    | implement App\Integrations\Contracts\Connector.
    |
    */

    'connectors' => [
        GithubConnector::class,
        GoogleConnector::class,
        DockerConnector::class,
        SonarrConnector::class,
        RadarrConnector::class,
        ProwlarrConnector::class,
        JellyseerrConnector::class,
        QbittorrentConnector::class,
        JellyfinConnector::class,
        ProxmoxConnector::class,
        HomeAssistantConnector::class,
        TelegramConnector::class,
        NotionConnector::class,
        RssConnector::class,
        RedditConnector::class,
        YoutubeConnector::class,
        ListenbrainzConnector::class,
        LocalStorageConnector::class,
        S3StorageConnector::class,
        SftpStorageConnector::class,
        FtpStorageConnector::class,
        GoogleDriveStorageConnector::class,
        DropboxStorageConnector::class,
        WebdavStorageConnector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval Queue
    |--------------------------------------------------------------------------
    |
    | Write and destructive actions requested by the AI agent are queued for
    | user approval. Pending requests expire after the configured TTL.
    |
    */

    'approval' => [
        'ttl_hours' => (int) env('INTEGRATIONS_APPROVAL_TTL_HOURS', 72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Connection Rate Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'read_per_minute' => (int) env('INTEGRATIONS_READ_PER_MINUTE', 60),
        'write_per_minute' => (int) env('INTEGRATIONS_WRITE_PER_MINUTE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSH Transport
    |--------------------------------------------------------------------------
    */

    'ssh' => [
        'connect_timeout' => (int) env('INTEGRATIONS_SSH_CONNECT_TIMEOUT', 5),
        'command_timeout' => (int) env('INTEGRATIONS_SSH_COMMAND_TIMEOUT', 15),
        'output_cap_bytes' => (int) env('INTEGRATIONS_SSH_OUTPUT_CAP', 262144),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Transport
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('INTEGRATIONS_HTTP_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log Retention
    |--------------------------------------------------------------------------
    */

    'retention_days' => (int) env('INTEGRATIONS_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Redaction Patterns
    |--------------------------------------------------------------------------
    |
    | Applied to error messages and result summaries before they are stored.
    |
    */

    'redaction' => [
        'patterns' => [
            '/\b(Bearer|token|api[_-]?key|secret|password)\b\s*[:=]?\s*[\w\-\.]{6,}/i',
        ],
    ],

];
