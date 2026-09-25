<?php

namespace App\Storage;

use App\Integrations\OAuth\OAuthBroker;
use App\Models\Connection;
use Aws\S3\S3Client;
use Google\Client as GoogleClient;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Masbug\Flysystem\GoogleDriveAdapter;
use Sabre\DAV\Client as SabreClient;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;
use Throwable;

class StorageManager
{
    public function for(Connection $connection): FilesystemOperator
    {
        return new Filesystem($this->adapterFor($connection));
    }

    public function adapterFor(Connection $connection): FilesystemAdapter
    {
        return match ($connection->kind) {
            'storage_local' => new LocalFilesystemAdapter(
                (string) ($connection->options['root'] ?? storage_path('app')),
            ),
            'storage_s3' => new AwsS3V3Adapter(
                $this->s3Client($connection),
                (string) ($connection->options['bucket'] ?? ''),
                (string) ($connection->options['prefix'] ?? ''),
            ),
            'storage_sftp' => new SftpAdapter(
                new SftpConnectionProvider(
                    host: $this->host($connection),
                    username: (string) ($connection->credentials['username'] ?? ''),
                    password: $connection->credentials['password'] ?? null,
                    privateKey: $connection->credentials['private_key'] ?? null,
                    port: (int) ($connection->options['port'] ?? 22),
                ),
                (string) ($connection->options['root'] ?? '/'),
            ),
            'storage_ftp' => new FtpAdapter(
                FtpConnectionOptions::fromArray([
                    'host' => $this->host($connection),
                    'username' => (string) ($connection->credentials['username'] ?? ''),
                    'password' => (string) ($connection->credentials['password'] ?? ''),
                    'port' => (int) ($connection->options['port'] ?? 21),
                    'root' => (string) ($connection->options['root'] ?? '/'),
                    'passive' => (bool) ($connection->options['passive'] ?? true),
                ]),
            ),
            'storage_google_drive' => new GoogleDriveAdapter(
                $this->googleClient($connection),
                $connection->options['root'] ?? null,
            ),
            'storage_dropbox' => new DropboxAdapter(
                new DropboxClient($this->freshToken($connection)),
            ),
            'storage_webdav' => new WebDAVAdapter(
                new SabreClient([
                    'baseUri' => rtrim((string) $connection->base_url, '/'),
                    'userName' => (string) ($connection->credentials['username'] ?? ''),
                    'password' => (string) ($connection->credentials['password'] ?? ''),
                ]),
                (string) ($connection->options['root'] ?? ''),
            ),
            default => throw new \InvalidArgumentException("Unsupported storage kind [{$connection->kind}]."),
        };
    }

    protected function s3Client(Connection $connection): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => (string) ($connection->options['region'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($connection->credentials['key'] ?? ''),
                'secret' => (string) ($connection->credentials['secret'] ?? ''),
            ],
        ];

        if (filled($connection->options['endpoint'] ?? null)) {
            $config['endpoint'] = $connection->options['endpoint'];
            $config['use_path_style_endpoint'] = (bool) ($connection->options['path_style'] ?? false);
        }

        return new S3Client($config);
    }

    protected function googleClient(Connection $connection): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.oauth.client_id'));
        $client->setClientSecret((string) config('services.google.oauth.client_secret'));
        $client->setAccessToken([
            'access_token' => $this->freshToken($connection),
            'refresh_token' => $connection->credentials['refresh_token'] ?? null,
            'expires_in' => 3600,
            'created' => time(),
        ]);

        return $client;
    }

    protected function freshToken(Connection $connection): string
    {
        if (in_array($connection->kind, ['storage_google_drive', 'storage_dropbox'], true)) {
            try {
                $connection = app(OAuthBroker::class)->refreshIfNeeded($connection);
            } catch (Throwable) {
                // Se usa el token actual si el refresh falla.
            }
        }

        return (string) ($connection->credentials['access_token'] ?? '');
    }

    protected function host(Connection $connection): string
    {
        $host = (string) $connection->base_url;

        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        return $host;
    }
}
