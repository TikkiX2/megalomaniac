<?php

namespace App\Ai\Tools;

use App\Ai\Web\TavilyClient;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebSearchTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Busca en la web en tiempo real (Tavily). Úsala cuando necesites información actual, noticias o datos que no están en la base de datos del usuario. '
            .'Devuelve resultados numerados; cita las fuentes en tu respuesta como [n] usando ese número y nunca inventes URLs.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return json_encode(['error' => 'Falta la consulta de búsqueda.'], JSON_UNESCAPED_UNICODE);
        }

        $client = TavilyClient::for($this->user);

        if (! $client instanceof TavilyClient) {
            return json_encode(
                ['error' => 'Configura tu key de Tavily en Ajustes → IA para buscar en la web.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        return json_encode($client->search($query, [
            'max_results' => $request['max_results'] ?? null,
            'topic' => $request['topic'] ?? null,
            'time_range' => $request['time_range'] ?? null,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Consulta de búsqueda web'),
            'max_results' => $schema->integer()->description('Número máximo de resultados (1-8)')->default(6),
            'topic' => $schema->string()
                ->enum(['general', 'news', 'finance'])
                ->description('Tipo de búsqueda; usa news para actualidad'),
            'time_range' => $schema->string()
                ->enum(['day', 'week', 'month', 'year'])
                ->description('Ventana temporal de los resultados'),
        ];
    }
}
