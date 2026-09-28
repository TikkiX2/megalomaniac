<?php

namespace App\Integrations\Mcp;

use App\Models\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Primitives\Prompt;
use Laravel\Mcp\Client\Primitives\Tool;

class McpDiscoveryService
{
    public function __construct(
        private readonly McpClientFactory $factory,
    ) {}

    /**
     * @return Collection<int, Tool>
     */
    public function tools(Connection $connection): Collection
    {
        return Cache::remember(
            $this->key($connection, 'tools'),
            now()->addMinutes(10),
            fn (): Collection => $this->factory->for($connection)->tools(),
        );
    }

    /**
     * @return Collection<int, \Laravel\Mcp\Client\Primitives\Resource>
     */
    public function resources(Connection $connection): Collection
    {
        return Cache::remember(
            $this->key($connection, 'resources'),
            now()->addMinutes(10),
            fn (): Collection => $this->factory->for($connection)->resources(),
        );
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function prompts(Connection $connection): Collection
    {
        return Cache::remember(
            $this->key($connection, 'prompts'),
            now()->addMinutes(10),
            fn (): Collection => $this->factory->for($connection)->prompts(),
        );
    }

    public function forget(Connection $connection): void
    {
        foreach (['tools', 'resources', 'prompts'] as $type) {
            Cache::forget($this->key($connection, $type));
        }
    }

    protected function key(Connection $connection, string $type): string
    {
        $version = $connection->updated_at?->timestamp ?? 0;

        return "mcp:{$type}:{$connection->id}:".sha1((string) $connection->base_url.'|'.$version);
    }
}
