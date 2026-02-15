<?php

use Laravel\Mcp\Facades\Mcp;
use App\Mcp\Servers\BarneyServer;

// Web server endpoint (HTTP)
Mcp::web('/barney', BarneyServer::class);

// Local server for Claude Desktop (stdio)
Mcp::local('barney', BarneyServer::class);
