<?php

namespace App\Mcp\Servers\PublicServer;

use Laravel\Mcp\Server;

class PublicServer extends Server
{
    protected string $name = 'Public MCP Server';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Public MCP server for the application.
    MARKDOWN;
}
