<?php

use App\Mcp\Servers\MegalomaniacServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/megalomaniac', MegalomaniacServer::class)->middleware(['auth:sanctum', 'throttle:mcp']);
