<?php

namespace App\Http\Controllers;

use App\Services\McpTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token del servidor MCP, gestionado por el propio admin desde la PWA (en el
 * hosting no hay consola para `php artisan mcp:token`).
 *
 * La ruta NO cuelga de /admin/mcp/*: el token MCP sólo pasa por esas rutas,
 * así que no puede usarse para emitirse otro ni para renovarse solo.
 */
class McpTokenController extends Controller
{
    public function __construct(private McpTokenService $tokens)
    {
    }

    public function estado(Request $request): JsonResponse
    {
        return response()->json($this->tokens->estado($request->user()));
    }

    /** El texto del token se muestra esta única vez; en la base queda hasheado. */
    public function emitir(Request $request): JsonResponse
    {
        return response()->json($this->tokens->emitir($request->user()), 201);
    }

    public function revocar(Request $request): JsonResponse
    {
        $this->tokens->revocar($request->user());

        return response()->json($this->tokens->estado($request->user()));
    }
}
