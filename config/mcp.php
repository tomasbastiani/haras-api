<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token del servidor MCP (Claude Desktop del admin)
    |--------------------------------------------------------------------------
    |
    | Un token de Sanctum aparte del de login, con la habilidad `mcp:read`,
    | que sólo sirve para las rutas /admin/mcp/* (ver AuthServiceProvider).
    | Vence solo: queda guardado en la PC del admin y, a diferencia del token
    | de la PWA, nadie lo cierra con un logout.
    |
    */

    'token_nombre' => 'claude-desktop',
    'token_dias'   => (int) env('MCP_TOKEN_DIAS', 90),

];
