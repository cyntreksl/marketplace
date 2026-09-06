<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDraftProductTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Marketplace')]
#[Version('0.0.1')]
#[Instructions('This server provides product management tools for the marketplace. Use the create-draft-product tool to create draft product listings for sellers.')]
class MarketplaceServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        CreateDraftProductTool::class,
    ];
}
