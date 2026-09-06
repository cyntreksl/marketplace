<?php

use App\Mcp\Servers\MarketplaceServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('marketplace', MarketplaceServer::class);
Mcp::web('/mcp/marketplace', MarketplaceServer::class);
