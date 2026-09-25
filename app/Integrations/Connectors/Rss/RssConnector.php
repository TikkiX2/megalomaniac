<?php

namespace App\Integrations\Connectors\Rss;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;
use SimpleXMLElement;

class RssConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'rss';
    }

    public function label(): string
    {
        return 'RSS / Atom';
    }

    public function group(): string
    {
        return 'Contenido';
    }

    public function description(): string
    {
        return 'Feeds RSS/Atom: cualquier blog, sitio o Hacker News.';
    }

    public function authFields(): array
    {
        return [];
    }

    public function actions(): array
    {
        return [
            new Action('feed.info', 'Ver feed', 'Título y descripción del feed', ActionAccess::Read),
            new Action('feed.fetch', 'Leer items', 'Últimos items del feed', ActionAccess::Read, [
                new Param('limit', 'integer', false, 'Cantidad de items', default: 20),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $response = $this->feedRequest($connection);

        if (! $response->ok) {
            return ActionResult::failure($response->error ?? 'No se pudo leer el feed.');
        }

        $parsed = $this->parse($response->body, (int) ($params['limit'] ?? 20));

        if ($parsed === null) {
            return ActionResult::failure('El feed no es XML válido o no tiene formato RSS/Atom.');
        }

        return match ($key) {
            'feed.info' => ActionResult::success('Feed obtenido.', [
                'title' => $parsed['title'],
                'description' => $parsed['description'],
                'link' => $parsed['link'],
            ]),
            'feed.fetch' => ActionResult::success('Items obtenidos.', [
                'title' => $parsed['title'],
                'items' => $parsed['items'],
            ]),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->feedRequest($connection);

        if (! $response->ok) {
            return ConnectionTestResult::fail($response->error ?? 'El feed no respondió.');
        }

        $parsed = $this->parse($response->body, 1);

        return $parsed === null
            ? ConnectionTestResult::fail('El feed no es XML RSS/Atom válido.')
            : ConnectionTestResult::ok('Feed OK', ['title' => $parsed['title']]);
    }

    protected function feedRequest(Connection $connection): HttpResult
    {
        $url = (string) ($connection->base_url ?? '');

        if ($url === '') {
            return new HttpResult(
                ok: false,
                status: 0,
                data: null,
                body: '',
                error: 'Falta la URL del feed.',
            );
        }

        return $this->request($connection, new HttpCall('GET', $url));
    }

    /**
     * @return array{title: string, description: string, link: string, items: array<int, array<string, mixed>>}|null
     */
    protected function parse(string $xml, int $limit): ?array
    {
        if (trim($xml) === '') {
            return null;
        }

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();

        if (! $document instanceof SimpleXMLElement) {
            return null;
        }

        $channel = $document->channel ?? $document;
        $isAtom = ! isset($document->channel);

        $items = [];

        foreach ($isAtom ? $channel->entry : $channel->item as $entry) {
            if (count($items) >= max(1, $limit)) {
                break;
            }

            $items[] = $isAtom ? $this->atomItem($entry) : $this->rssItem($entry);
        }

        return [
            'title' => trim((string) ($channel->title ?? '')),
            'description' => trim((string) ($channel->description ?? $channel->subtitle ?? '')),
            'link' => $isAtom
                ? trim((string) ($channel->link['href'] ?? ''))
                : trim((string) ($channel->link ?? '')),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rssItem(SimpleXMLElement $item): array
    {
        $url = trim((string) ($item->link ?? ''));

        return [
            'external_id' => trim((string) ($item->guid ?? '')) ?: sha1($url),
            'title' => trim((string) ($item->title ?? '')),
            'url' => $url,
            'summary' => trim(strip_tags((string) ($item->description ?? ''))),
            'author' => trim((string) ($item->author ?? $item->children('dc', true)->creator ?? '')),
            'published_at' => ($timestamp = strtotime((string) ($item->pubDate ?? ''))) ? date('c', $timestamp) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function atomItem(SimpleXMLElement $entry): array
    {
        $url = trim((string) ($entry->link['href'] ?? ''));

        return [
            'external_id' => trim((string) ($entry->id ?? '')) ?: sha1($url),
            'title' => trim((string) ($entry->title ?? '')),
            'url' => $url,
            'summary' => trim(strip_tags((string) ($entry->summary ?? $entry->content ?? ''))),
            'author' => trim((string) ($entry->author->name ?? '')),
            'published_at' => ($value = trim((string) ($entry->published ?? $entry->updated ?? ''))) !== ''
                ? date('c', strtotime($value))
                : null,
        ];
    }
}
