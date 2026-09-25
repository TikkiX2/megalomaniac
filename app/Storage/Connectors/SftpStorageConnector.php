<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class SftpStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_sftp';
    }

    public function label(): string
    {
        return 'SFTP';
    }

    public function description(): string
    {
        return 'Servidor SFTP.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'username', type: 'text', label: 'Usuario', required: true),
            new AuthField(name: 'password', type: 'password', label: 'Contraseña', required: false),
        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'port', 'label' => 'Puerto', 'placeholder' => '22', 'required' => true],
            ['name' => 'root', 'label' => 'Carpeta raíz', 'placeholder' => '/home/user', 'required' => false],
        ];
    }
}
