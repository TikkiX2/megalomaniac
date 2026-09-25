<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class WebdavStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_webdav';
    }

    public function label(): string
    {
        return 'WebDAV / Nextcloud';
    }

    public function description(): string
    {
        return 'Nextcloud, ownCloud y cualquier WebDAV.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'username', type: 'text', label: 'Usuario', required: true),
            new AuthField(name: 'password', type: 'password', label: 'Contraseña', required: true),
        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'root', 'label' => 'Carpeta raíz', 'placeholder' => 'remote.php/dav/files/user', 'required' => false],
        ];
    }
}
