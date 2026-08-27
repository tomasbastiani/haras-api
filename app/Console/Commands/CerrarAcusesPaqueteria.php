<?php

namespace App\Console\Commands;

use App\Models\PaqueteEntrega;
use App\Models\PaqueteEvento;
use Illuminate\Console\Command;

/**
 * Cierra como tácitos los acuses que el titular no respondió en el plazo.
 *
 * Se registran como 'tacito' y no como 'confirmado' a propósito: un silencio
 * no es una confirmación expresa, y hacerlo pasar por tal es justamente lo que
 * le haría perder valor a todo el expediente si alguna vez se discute.
 */
class CerrarAcusesPaqueteria extends Command
{
    protected $signature = 'paqueteria:cerrar-acuses';

    protected $description = 'Marca como tácitos los acuses de recibo vencidos';

    public function handle(): int
    {
        $horas = (int) config('paqueteria.ack_horas');
        $limite = now()->subHours($horas);

        $pendientes = PaqueteEntrega::where('ack_estado', 'pendiente')
            ->where('entregado_at', '<=', $limite)
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay acuses vencidos.');

            return self::SUCCESS;
        }

        foreach ($pendientes as $entrega) {
            $entrega->update([
                'ack_estado' => 'tacito',
                'ack_at'     => now(),
            ]);

            PaqueteEvento::registrar(
                $entrega->paquete_id,
                'ack_tacito',
                "El titular no respondió en {$horas} horas: se cierra como acuse tácito",
                null,
                ['folio' => $entrega->folio, 'horas' => $horas]
            );
        }

        $this->info("Se cerraron {$pendientes->count()} acuse(s) como tácitos.");

        return self::SUCCESS;
    }
}
