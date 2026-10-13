<?php

namespace App\Services;

use App\Models\User;

/**
 * Emite y revoca el token con el que el servidor MCP del admin consulta la
 * API. Un token vigente por admin: emitir uno nuevo revoca el anterior, así
 * una PC perdida o un archivo de config filtrado se corta generando otro.
 */
class McpTokenService
{
    public const HABILIDAD = 'mcp:read';

    public function emitir(User $user): array
    {
        $this->revocar($user);

        $vence = now()->addDays(config('mcp.token_dias'));
        $token = $user->createToken(config('mcp.token_nombre'), [self::HABILIDAD], $vence);

        return [
            'token'     => $token->plainTextToken,
            'vence_at'  => $vence->toIso8601String(),
        ];
    }

    public function revocar(User $user): int
    {
        return $user->tokens()->where('name', config('mcp.token_nombre'))->delete();
    }

    public function estado(User $user): array
    {
        $token = $user->tokens()->where('name', config('mcp.token_nombre'))->latest('id')->first();

        return [
            'activo'       => $token !== null && ! optional($token->expires_at)->isPast(),
            'creado_at'    => optional(optional($token)->created_at)->toIso8601String(),
            'vence_at'     => optional(optional($token)->expires_at)->toIso8601String(),
            'ultimo_uso_at' => optional(optional($token)->last_used_at)->toIso8601String(),
        ];
    }
}
