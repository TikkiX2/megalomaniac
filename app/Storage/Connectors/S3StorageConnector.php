<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class S3StorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_s3';
    }

    public function label(): string
    {
        return 'Amazon S3 / compatible';
    }

    public function description(): string
    {
        return 'S3, MinIO, R2 y otros compatibles.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'key', type: 'password', label: 'Access key', required: true),
            new AuthField(name: 'secret', type: 'password', label: 'Secret key', required: true),
        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'bucket', 'label' => 'Bucket', 'placeholder' => 'mi-bucket', 'required' => true],
            ['name' => 'region', 'label' => 'Región', 'placeholder' => 'us-east-1', 'required' => true],
            ['name' => 'endpoint', 'label' => 'Endpoint (opcional)', 'placeholder' => 'https://s3.example.com', 'required' => false],
            ['name' => 'prefix', 'label' => 'Prefijo (opcional)', 'placeholder' => 'backups/', 'required' => false],
        ];
    }
}
