<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Sincroniza users.email_rebotado_at con la lista de suprimidas de Postmark.
 *
 * - Marca a los usuarios cuyo email Postmark suprimió por hard bounce.
 * - Desmarca a los que Postmark ya no tiene suprimidos (reactivados a mano).
 *
 * Postmark es la fuente de verdad: ellos ya no le mandan a esas direcciones,
 * esto es para que la app tampoco lo intente y para que el admin sepa quién
 * tiene el email muerto. Sólo cuenta HardBounce: una baja voluntaria
 * (unsubscribe) no es un email roto.
 *
 * Si Postmark no responde bien en CUALQUIERA de los streams, no se toca nada:
 * desmarcar contra una lista incompleta borraría marcas válidas.
 *
 * Corre todos los días desde el scheduler. A mano:
 *   php artisan mail:sincronizar-rebotes --dry-run
 */
class SincronizarRebotesMail extends Command
{
    protected $signature = 'mail:sincronizar-rebotes {--dry-run : Sólo muestra qué cambiaría, sin tocar la base}';

    protected $description = 'Marca/desmarca users.email_rebotado_at según los hard bounces suprimidos en Postmark';

    public function handle(): int
    {
        $token = config('services.postmark.token');
        if (! $token) {
            $this->error('Falta el token de Postmark (POSTMARK_TOKEN o MAIL_USERNAME).');
            return self::FAILURE;
        }

        $streams = array_values(array_unique(array_filter(['outbound', config('mail.stream_masivo')])));

        $rebotados = [];
        foreach ($streams as $stream) {
            $resp = Http::withHeaders([
                'Accept'                  => 'application/json',
                'X-Postmark-Server-Token' => $token,
            ])->timeout(30)->get("https://api.postmarkapp.com/message-streams/{$stream}/suppressions/dump");

            $lista = $resp->json('Suppressions');
            if (! $resp->successful() || ! is_array($lista)) {
                $this->error("Postmark no devolvió la lista del stream '{$stream}' (HTTP {$resp->status()}). No se modificó nada.");
                return self::FAILURE;
            }

            foreach ($lista as $s) {
                if (($s['SuppressionReason'] ?? null) === 'HardBounce' && ! empty($s['EmailAddress'])) {
                    $rebotados[strtolower(trim($s['EmailAddress']))] = true;
                }
            }

            $this->line("Stream {$stream}: " . count($lista) . ' suprimidas.');
        }

        $rebotados = array_keys($rebotados);
        $this->line('Hard bounces en Postmark: ' . count($rebotados));

        $emailNorm = DB::raw('LOWER(TRIM(email))');

        $aMarcar = User::query()
            ->whereNull('email_rebotado_at')
            ->whereIn($emailNorm, $rebotados ?: [''])
            ->get(['id', 'email']);

        $aDesmarcar = User::query()
            ->whereNotNull('email_rebotado_at')
            ->whereNotIn($emailNorm, $rebotados ?: [''])
            ->get(['id', 'email']);

        $this->line("A marcar: {$aMarcar->count()} · a desmarcar: {$aDesmarcar->count()}");

        if ($this->option('dry-run')) {
            foreach ($aMarcar as $u) {
                $this->line("  + #{$u->id} {$u->email}");
            }
            foreach ($aDesmarcar as $u) {
                $this->line("  - #{$u->id} {$u->email}");
            }
            $this->comment('Dry run: no se modificó nada.');
            return self::SUCCESS;
        }

        // Por query builder a propósito: no cambia el email, así que no hace
        // falta (ni conviene) disparar los eventos del modelo.
        if ($aMarcar->isNotEmpty()) {
            DB::table('users')->whereIn('id', $aMarcar->pluck('id'))->update(['email_rebotado_at' => now()]);
        }
        if ($aDesmarcar->isNotEmpty()) {
            DB::table('users')->whereIn('id', $aDesmarcar->pluck('id'))->update(['email_rebotado_at' => null]);
        }

        $this->info("Listo. Marcados: {$aMarcar->count()} · desmarcados: {$aDesmarcar->count()}");

        return self::SUCCESS;
    }
}
