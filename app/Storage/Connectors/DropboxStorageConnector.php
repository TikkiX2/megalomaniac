<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class DropboxStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_dropbox';
    }

    public function label(): string
    {
        return 'Dropbox';
    }

    public function description(): string
    {
        return 'Dropbox del usuario vía OAuth.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'oauth', type: 'oauth', label: 'Conectar con Dropbox', required: true, help: 'Requiere DROPBOX_CLIENT_ID/SECRET en el entorno.'),
        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'root', 'label' => 'Carpeta raíz (opcional)', 'placeholder' => '/Apps/Megalomaniac', 'required' => false],
        ];
    }
}
