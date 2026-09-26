<?php

namespace App\Ai\Tools;

use App\Ai\Web\TavilyClient;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebFetchTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Lee el contenido completo de una o varias páginas web (http/https) y lo devuelve en markdown. '
            .'Úsala cuando tengas una URL concreta (por ejemplo de un resultado de búsqueda) y necesites su contenido. '
            .'Cita las fuentes en tu respuesta con la URL y nunca inventes URLs.';
    }

    public function handle(Request $request): Stringable|string
    {
        $urls = array_values(array_filter(
            (array) ($request['urls'] ?? []),
            fn (mixed $url): bool => is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true),
        ));

        if ($urls === []) {
            return json_encode(
                ['error' => 'Proporciona al menos una URL http(s) válida.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        $client = TavilyClient::for($this->user);

        if (! $client instanceof TavilyClient) {
            return json_encode(
                ['error' => 'Configura tu key de Tavily en Ajustes → IA para leer páginas web.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        $query = $request['query'] ?? null;

        return json_encode(
            $client->extract($urls, filled($query) ? (string) $query : null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'urls' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(5)
                ->required()
                ->description('URLs http(s) a leer (1-5)'),
            'query' => $schema->string()->description('Qué información buscar dentro de las páginas'),
        ];
    }
}
