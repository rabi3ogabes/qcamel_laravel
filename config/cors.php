<?php

/*
| F-API-006 rebuild: the web app is same-origin (session + CSRF) and needs no
| CORS. Only the bearer-token surfaces are open cross-origin, never with
| credentials: REST API v1 and the MCP server. Webhooks need none.
*/
return [
    'paths' => ['api/v1/*', 'mcp'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'Mcp-Session-Id', 'Mcp-Protocol-Version'],

    'exposed_headers' => ['Mcp-Session-Id'],

    'max_age' => 86400,

    'supports_credentials' => false,
];
