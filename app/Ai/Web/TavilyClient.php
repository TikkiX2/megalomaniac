<?php

namespace App\Ai\Web;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class TavilyClient
{
    private const CONNECTION_ERROR = 'No se pudo conectar con Tavily. Comprueba tu conexión e inténtalo de nuevo.';

    public function __construct(private readonly string $key) {}

    /**
     * Resolve a client from the user's encrypted key or the server fallback.
     */
    public static function for(User $user): ?self
    {
        $key = $user->tavily_api_key ?: config('services.tavily.key');

        return filled($key) ? new self((string) $key) : null;
    }

    /**
     * @param  array{max_results?: int|string|null, topic?: string|null, time_range?: string|null}  $options
     * @return array{query: string, results: array<int, array<string, mixed>>}|array{error: string}
     */
    public function search(string $query, array $options = []): array
    {
        try {
            $response = $this->request()
                ->timeout(15)
                ->post('/search', array_filter([
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => min(8, max(1, (int) ($options['max_results'] ?? 6))),
                    'topic' => $options['topic'] ?? null,
                    'time_range' => $options['time_range'] ?? null,
                    'include_favicon' => true,
                ], fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException) {
            return ['error' => self::CONNECTION_ERROR];
        }

        if ($response->failed()) {
            return ['error' => $this->errorMessage($response->status())];
        }

        $results = collect($response->json('results', []))
            ->values()
            ->map(fn (array $result, int $index): array => [
                'n' => $index + 1,
                'title' => (string) ($result['title'] ?? ''),
                'url' => (string) ($result['url'] ?? ''),
                'content' => mb_substr((string) ($result['content'] ?? ''), 0, 4000),
                'published_date' => $result['published_date'] ?? null,
                'favicon' => $result['favicon'] ?? null,
            ])
            ->all();

        return ['query' => $query, 'results' => $results];
    }

    /**
     * @param  array<int, string>  $urls
     * @return array{pages: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>}|array{error: string}
     */
    public function extract(array $urls, ?string $query = null): array
    {
        try {
            $response = $this->request()
                ->timeout(20)
                ->post('/extract', array_filter([
                    'urls' => array_slice(array_values($urls), 0, 5),
                    'query' => $query,
                    'format' => 'markdown',
                    'extract_depth' => 'basic',
                    'timeout' => 20,
                ], fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException) {
            return ['error' => self::CONNECTION_ERROR];
        }

        if ($response->failed()) {
            return ['error' => $this->errorMessage($response->status())];
        }

        $pages = collect($response->json('results', []))
            ->values()
            ->map(fn (array $page): array => [
                'url' => (string) ($page['url'] ?? ''),
                'content' => mb_substr((string) ($page['raw_content'] ?? ''), 0, 15000),
                'favicon' => $page['favicon'] ?? null,
            ])
            ->all();

        $failed = collect($response->json('failed_results', []))
            ->values()
            ->map(fn (array $failure): array => [
                'url' => (string) ($failure['url'] ?? ''),
                'error' => (string) ($failure['error'] ?? 'No se pudo extraer el contenido.'),
            ])
            ->all();

        return ['pages' => $pages, 'failed' => $failed];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.tavily.url'))
            ->withToken($this->key)
            ->acceptJson();
    }

    private function errorMessage(int $status): string
    {
        return match ($status) {
            401, 403 => 'Tu key de Tavily no es válida. Revísala en Ajustes → IA.',
            429 => 'Demasiadas peticiones a Tavily. Espera unos segundos e inténtalo de nuevo.',
            432, 433 => 'Tu plan de Tavily alcanzó su límite. Revisa tu cuenta en tavily.com.',
            default => 'No se pudo contactar con Tavily (HTTP '.$status.'). Inténtalo de nuevo más tarde.',
        };
    }
}
