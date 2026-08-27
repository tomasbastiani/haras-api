<?php

namespace App\Console\Commands;

use App\Models\PaqueteEvento;
use Illuminate\Console\Command;

/**
 * Auditoría de la bitácora de paquetería.
 *
 * Recorre los eventos en orden y revisa que cada uno cierre con el anterior y
 * que sus columnas coincidan con el payload sellado. Cualquier edición o
 * borrado hecho por fuera de la aplicación aparece acá.
 */
class VerificarCadenaPaquetes extends Command
{
    protected $signature = 'paqueteria:verificar-cadena {--desde=1 : ID de evento desde el que verificar}';

    protected $description = 'Verifica la integridad de la bitácora sellada de paquetería';

    public function handle(): int
    {
        $desde = (int) $this->option('desde');

        $total = PaqueteEvento::where('id', '>=', $desde)->count();

        if ($total === 0) {
            $this->info('No hay eventos para verificar.');

            return self::SUCCESS;
        }

        $this->info("Verificando {$total} evento(s)...");
        $barra = $this->output->createProgressBar($total);

        // Al arrancar desde el medio no se puede saber cuál era el hash previo
        // esperado, así que ese enganche se valida sólo desde el primer evento.
        $hashEsperado = $desde <= 1 ? PaqueteEvento::HASH_GENESIS : null;
        $rotos = [];

        PaqueteEvento::where('id', '>=', $desde)
            ->orderBy('id')
            ->chunk(500, function ($eventos) use (&$hashEsperado, &$rotos, $barra) {
                foreach ($eventos as $evento) {
                    $problemas = $evento->problemas($hashEsperado);

                    if ($problemas) {
                        $rotos[] = [$evento->id, $evento->paquete_id, implode('; ', $problemas)];
                    }

                    $hashEsperado = $evento->hash;
                    $barra->advance();
                }
            });

        $barra->finish();
        $this->newLine(2);

        if (empty($rotos)) {
            $this->info("✅ Cadena íntegra: {$total} evento(s) verificados sin alteraciones.");

            return self::SUCCESS;
        }

        $this->error('❌ Se detectaron eventos alterados:');
        $this->table(['Evento', 'Paquete', 'Problema'], $rotos);

        return self::FAILURE;
    }
}
