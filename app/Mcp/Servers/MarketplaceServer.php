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
#[Version('0.5.0')]
#[Instructions('This server provides product management tools for the marketplace. Use search-product-categories when a selectable category ID is unknown, then use create-draft-product with complete product details. When the user asks to generate a product image, pass a concise image_generation_prompt to create-draft-product so ProDeals generates and attaches it in the same call; do not call a separate image-generation tool first. Use update-draft-product to change the title, short description, description, specifications_text, or warranty and optionally attach images to an existing draft. Supply its listing_id and only the fields to change; omitted fields are preserved. Both create and update automatically generate missing SEO meta_title and meta_description; updates refresh them when source content changes. Supply explicit meta_title or meta_description to override generation. Never create a duplicate listing to update an existing draft. Creation and updates always stop at draft status for seller review.')]
class MarketplaceServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        SearchProductCategoriesTool::class,
        CreateDraftProductTool::class,
        UpdateDraftProductTool::class,
    ];
}
