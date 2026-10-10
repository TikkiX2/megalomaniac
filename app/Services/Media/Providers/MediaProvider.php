<?php

namespace App\Services\Media\Providers;

use App\Services\Media\MediaSearchResult;

interface MediaProvider
{
    /**
     * Busca en el provider externo y normaliza los resultados.
     *
     * Lanza ConnectionException|RequestException ante errores de red o HTTP:
     * quien decide la degradación a [] es MediaSearchService.
     *
     * @return array<int, MediaSearchResult>
     */
    public function search(string $query): array;
}
