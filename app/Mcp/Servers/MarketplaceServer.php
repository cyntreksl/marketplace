<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDraftProductTool;
use App\Mcp\Tools\SearchProductCategoriesTool;
use App\Mcp\Tools\UpdateDraftProductTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Marketplace')]
#[Version('0.2.0')]
#[Instructions('This server provides product management tools for the marketplace. Use search-product-categories when a selectable category ID is unknown, then use create-draft-product with complete product details. Use update-draft-product to attach public HTTPS images or base64 file contents to an existing draft. Creation and updates always stop at draft status so the seller can perform final review before submission or publishing.')]
class MarketplaceServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        SearchProductCategoriesTool::class,
        CreateDraftProductTool::class,
        UpdateDraftProductTool::class,
    ];
}
