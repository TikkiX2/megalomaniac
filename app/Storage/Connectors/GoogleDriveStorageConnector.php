<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class GoogleDriveStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_google_drive';
    }

    public function label(): string
    {
        return 'Google Drive';
    }

    public function description(): string
    {
        return 'Drive del usuario vía OAuth.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'oauth', type: 'oauth', label: 'Conectar con Google', required: true, help: 'Se piden permisos de Drive.'),
        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'root', 'label' => 'Carpeta raíz (ID, opcional)', 'placeholder' => '1AbC...', 'required' => false],
        ];
    }
}
