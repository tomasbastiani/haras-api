<?php

namespace App\Console\Commands;

use App\Services\EnviosMasivosService;
use Illuminate\Console\Command;

/**
 * Manda la próxima tanda de mails masivos encolados.
 *
 * Corre cada minuto desde el scheduler. Hosting compartido: no hay worker de
 * cola, así que el "worker" es esto. Si no hay nada pendiente sale enseguida.
 */
class ProcesarEnviosMasivos extends Command
{
    protected $signature = 'envios:procesar {--limite= : Máximo de mails en esta pasada (por defecto mail.masivos.por_minuto)}';

    protected $description = 'Manda la próxima tanda de mails masivos encolados (gastos comunes, mail personalizado)';

    public function handle(EnviosMasivosService $envios): int
    {
        $limite = (int) ($this->option('limite') ?: config('mail.masivos.por_minuto', 50));

        $res = $envios->procesar(max(1, $limite));

        if (array_sum($res) > 0) {
            $this->info("Enviados: {$res['enviados']} · errores: {$res['errores']} · omitidos: {$res['omitidos']}");
        }

        return self::SUCCESS;
    }
}
