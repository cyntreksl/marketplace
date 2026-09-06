<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDraftProductTool;
use App\Mcp\Tools\SearchProductCategoriesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Marketplace')]
#[Version('0.1.0')]
#[Instructions('This server provides product management tools for the marketplace. Use search-product-categories when a selectable category ID is unknown, then use create-draft-product with complete product details, public HTTPS image URLs, variant options, and optional variant image URLs. Creation always stops at draft status so the seller can perform final review before submission or publishing.')]
class MarketplaceServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        SearchProductCategoriesTool::class,
        CreateDraftProductTool::class,
    ];
}
