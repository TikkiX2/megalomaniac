<?php

namespace App\Storage\Connectors;

use App\Integrations\Actions\AuthField;
use App\Storage\AbstractStorageConnector;

class FtpStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_ftp';
    }

    public function label(): string
    {
        return 'FTP';
    }

    public function description(): string
    {
        return 'Servidor FTP.';
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
            ['name' => 'port', 'label' => 'Puerto', 'placeholder' => '21', 'required' => true],
            ['name' => 'root', 'label' => 'Carpeta raíz', 'placeholder' => '/public_html', 'required' => false],
        ];
    }
}
