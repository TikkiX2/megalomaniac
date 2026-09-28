<?php

namespace App\Integrations\Contracts;

use App\Integrations\Actions\Action;
use App\Models\Connection;

/**
 * Optional interface for connectors whose available actions depend on the
 * connection (e.g. dynamic MCP servers). The executor and catalog prefer
 * actionsFor() when the connector implements it.
 */
interface ConnectionAwareConnector extends Connector
{
    /** @return Action[] */
    public function actionsFor(Connection $connection): array;
}
