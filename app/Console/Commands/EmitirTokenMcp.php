<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\McpTokenService;
use Illuminate\Console\Command;

/**
 * Para desarrollo local. En producción el admin lo genera desde la PWA
 * (Administración → Asistente Claude), porque el hosting no tiene consola.
 */
class EmitirTokenMcp extends Command
{
    protected $signature = 'mcp:token {email : Email del admin} {--revocar : Sólo revocar el token vigente}';

    protected $description = 'Emite (o revoca) el token de sólo lectura del servidor MCP para un admin';

    public function handle(McpTokenService $tokens): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user || (int) $user->admin !== 1) {
            $this->error('No existe un admin con ese email.');
            return self::FAILURE;
        }

        if ($this->option('revocar')) {
            $this->info('Tokens revocados: ' . $tokens->revocar($user));
            return self::SUCCESS;
        }

        $emitido = $tokens->emitir($user);

        $this->line('Token (se muestra una sola vez; el anterior quedó revocado):');
        $this->line($emitido['token']);
        $this->line('Vence: ' . $emitido['vence_at']);

        return self::SUCCESS;
    }
}
