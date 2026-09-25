<?php

namespace App\Storage\Connectors;

use App\Storage\AbstractStorageConnector;

class LocalStorageConnector extends AbstractStorageConnector
{
    public function kind(): string
    {
        return 'storage_local';
    }

    public function label(): string
    {
        return 'Almacenamiento local';
    }

    public function description(): string
    {
        return 'Carpeta del servidor donde corre la app.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [

        ];
    }

    public function optionFields(): array
    {
        return [
            ['name' => 'root', 'label' => 'Carpeta raíz', 'placeholder' => '/var/www/megalomaniac/storage/app/files', 'required' => true],
        ];
    }
}
