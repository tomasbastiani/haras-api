<?php

namespace App\Services;

use App\Mail\CustomAdminMail;
use App\Models\EnvioMasivo;
use App\Models\EnvioMasivoDestinatario;
use App\Models\GastosComunes;
use App\Models\InfoPago;
use App\Models\Moroso;
use App\Models\User;
use App\Notifications\GastosComunesDisponiblesNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Envíos masivos de mail: se encolan en el request y los manda el comando
 * `envios:procesar` de a tandas (ver la migración create_envios_masivos_tables
 * para el porqué).
 */
class EnviosMasivosService
{
    /**
     * Crea el envío y sus destinatarios.
     *
     * Las direcciones marcadas como rebotadas quedan en estado `omitido` desde
     * el arranque: así el admin ve cuántas se saltearon y por qué.
     *
     * @param  string[]  $emails
     * @return array{0: EnvioMasivo, 1: bool}  [envío, creado]. Si la clave ya
     *         existía devuelve el envío existente y `false`: no se encola nada.
     */
    public function encolar(string $tipo, string $clave, array $emails, array $datos, ?int $userId): array
    {
        $emails = $this->normalizar($emails);

        if ($existente = EnvioMasivo::where('clave', $clave)->first()) {
            return [$existente, false];
        }

        $rebotados = array_flip(User::emailsRebotados($emails));

        try {
            $envio = DB::transaction(function () use ($tipo, $clave, $emails, $datos, $userId, $rebotados) {
                $envio = EnvioMasivo::create([
                    'tipo'       => $tipo,
                    'clave'      => $clave,
                    'periodo'    => $datos['periodo'] ?? null,
                    'asunto'     => $datos['asunto'] ?? null,
                    'cuerpo'     => $datos['cuerpo'] ?? null,
                    'creado_por' => $userId,
                    'total'      => count($emails),
                ]);

                $ahora = now();
                $filas = array_map(fn ($email) => [
                    'envio_masivo_id' => $envio->id,
                    'email'           => $email,
                    'estado'          => isset($rebotados[$email])
                        ? EnvioMasivoDestinatario::OMITIDO
                        : EnvioMasivoDestinatario::PENDIENTE,
                    'detalle'         => isset($rebotados[$email]) ? 'Email marcado como rebotado' : null,
                    'created_at'      => $ahora,
                    'updated_at'      => $ahora,
                ], $emails);

                foreach (array_chunk($filas, 500) as $tanda) {
                    EnvioMasivoDestinatario::insert($tanda);
                }

                return $envio;
            });
        } catch (QueryException $e) {
            // Dos clics casi simultáneos: el segundo choca contra la clave
            // única. Devolvemos el envío que ganó.
            if ($existente = EnvioMasivo::where('clave', $clave)->first()) {
                return [$existente, false];
            }
            throw $e;
        }

        if ($envio->destinatarios()->where('estado', EnvioMasivoDestinatario::PENDIENTE)->doesntExist()) {
            $envio->update(['finalizado_at' => now()]);
        }

        Log::info('Envío masivo encolado', [
            'id' => $envio->id, 'tipo' => $tipo, 'clave' => $clave,
            'total' => count($emails), 'omitidos' => count($rebotados), 'por' => $userId,
        ]);

        return [$envio, true];
    }

    /**
     * Clave del aviso de gastos comunes: uno por período. Con `forzar` se
     * genera una clave nueva para reenviar a propósito.
     */
    public function claveGastos(?string $periodo, bool $forzar = false): string
    {
        return 'gastos_comunes:' . ($periodo ?? 'sin-periodo') . ($forzar ? ':' . now()->format('YmdHis') : '');
    }

    /**
     * Clave del mail personalizado: mismo asunto + cuerpo + destinatarios =
     * mismo envío. Frena el doble clic y el "reintentar" después de un error
     * de red, que eran los que duplicaban.
     */
    public function clavePersonalizado(string $asunto, string $cuerpo, array $emails, bool $forzar = false): string
    {
        $emails = $this->normalizar($emails);
        sort($emails);

        return 'personalizado:' . sha1($asunto . "\0" . $cuerpo . "\0" . implode(',', $emails))
            . ($forzar ? ':' . now()->format('YmdHis') : '');
    }

    /**
     * Manda hasta `$limite` destinatarios pendientes, en orden de llegada.
     *
     * @return array{enviados: int, errores: int, omitidos: int}
     */
    public function procesar(int $limite): array
    {
        $maxIntentos = max(1, (int) config('mail.masivos.max_intentos', 3));
        $res = ['enviados' => 0, 'errores' => 0, 'omitidos' => 0];

        $pendientes = EnvioMasivoDestinatario::with('envio')
            ->where('estado', EnvioMasivoDestinatario::PENDIENTE)
            ->orderBy('id')
            ->limit($limite)
            ->get();

        if ($pendientes->isEmpty()) {
            return $res;
        }

        // Se vuelve a mirar al momento de mandar: si la sincronización con
        // Postmark marcó una dirección después de encolar, igual se saltea.
        $rebotados = array_flip(User::emailsRebotados($pendientes->pluck('email')->all()));

        foreach ($pendientes as $dest) {
            if (isset($rebotados[$dest->email])) {
                $dest->update(['estado' => EnvioMasivoDestinatario::OMITIDO, 'detalle' => 'Email marcado como rebotado']);
                $res['omitidos']++;
                continue;
            }

            try {
                $this->enviarUno($dest->envio, $dest->email);

                $dest->update([
                    'estado'     => EnvioMasivoDestinatario::ENVIADO,
                    'intentos'   => $dest->intentos + 1,
                    'enviado_at' => now(),
                    'detalle'    => null,
                ]);
                $res['enviados']++;
            } catch (\Throwable $e) {
                $intentos = $dest->intentos + 1;

                $dest->update([
                    'estado'   => $intentos >= $maxIntentos ? EnvioMasivoDestinatario::ERROR : EnvioMasivoDestinatario::PENDIENTE,
                    'intentos' => $intentos,
                    'detalle'  => mb_substr($e->getMessage(), 0, 1000),
                ]);
                $res['errores']++;

                Log::error('Envío masivo: error mandando a un destinatario', [
                    'envio' => $dest->envio_masivo_id, 'email' => $dest->email,
                    'intento' => $intentos, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->cerrarTerminados($pendientes->pluck('envio_masivo_id')->unique()->all());

        return $res;
    }

    private function enviarUno(EnvioMasivo $envio, string $email): void
    {
        switch ($envio->tipo) {
            case EnvioMasivo::TIPO_GASTOS_COMUNES:
                Notification::route('mail', $email)
                    ->notify(new GastosComunesDisponiblesNotification($envio->periodo));
                return;

            case EnvioMasivo::TIPO_PERSONALIZADO:
                Mail::to($email)->send(new CustomAdminMail(
                    (string) $envio->asunto,
                    $this->cuerpoPersonalizado($email, (string) $envio->cuerpo)
                ));
                return;
        }

        throw new \RuntimeException("Tipo de envío desconocido: {$envio->tipo}");
    }

    private function cerrarTerminados(array $envioIds): void
    {
        foreach ($envioIds as $id) {
            $quedan = EnvioMasivoDestinatario::where('envio_masivo_id', $id)
                ->where('estado', EnvioMasivoDestinatario::PENDIENTE)
                ->exists();

            if (! $quedan) {
                EnvioMasivo::whereKey($id)->whereNull('finalizado_at')->update(['finalizado_at' => now()]);
            }
        }
    }

    /**
     * Cuerpo del mail personalizado para un destinatario: reemplaza los
     * placeholders con sus datos (lotes, deuda por lote, CVU/alias).
     * Movido tal cual desde AdminMailController.
     */
    public function cuerpoPersonalizado(string $email, string $bodyTpl): string
    {
        // ===== USERS =====
        $user = User::where('email', $email)->first();
        $nombre = $user?->name
            ?? $user?->nombre
            ?? '';

        // ===== 1) GASTOS COMUNES (NLotes únicos) =====
        $lotesNormales = GastosComunes::where('email', $email)
            ->distinct()
            ->pluck('nlote')
            ->toArray();

        // ===== 2) MOROSOS =====
        $morosos = Moroso::where('email', $email)->get();
        $lotesMorosos = $morosos->pluck('nlote')->unique()->toArray();
        $nombremoroso = $morosos->first()?->nombre ?? '';

        // Mapa lote => monto (deuda)
        $montosPorLote = [];
        foreach ($morosos as $m) {
            $montosPorLote[$m->nlote] = $m->monto;
        }

        // ===== 3) INFO PAGOS: CVU y ALIAS SOLO para lotes morosos =====
        $pagosPorLote = [];
        foreach (InfoPago::whereIn('nlote', $lotesMorosos)->get() as $ip) {
            $pagosPorLote[$ip->nlote] = [
                'cvu'   => $ip->cvu ?? '',
                'alias' => $ip->alias ?? '',
            ];
        }

        // ===== 4) Texto SOLO para lotes con deuda =====
        $detallePorLote = [];
        foreach ($lotesMorosos as $nl) {
            $monto = $montosPorLote[$nl] ?? null;

            if (empty($monto) || (float) $monto == 0.0) {
                continue;
            }

            $cvu   = $pagosPorLote[$nl]['cvu']   ?? '';
            $alias = $pagosPorLote[$nl]['alias'] ?? '';

            $detallePorLote[] =
                "Lote: $nl\n" .
                "Monto: $$monto\n" .
                "Para proceder con el pago correspondiente, le solicitamos realizar la transferencia a la siguiente cuenta bancaria: \n" .
                "- CVU: $cvu\n" .
                "- Alias: $alias\n";
        }

        // ===== 5) PLACEHOLDERS =====
        return strtr($bodyTpl, [
            '{nombre}'            => $nombre,
            '{nombremoroso}'      => $nombremoroso,
            '{lote}'              => implode(', ', $lotesNormales),
            '{lotemoroso}'        => implode(', ', $lotesMorosos),
            '{detalledeudaxlote}' => implode("\n\n", $detallePorLote),
        ]);
    }

    /**
     * Minúscula, sin espacios, sin repetidos y sólo direcciones válidas.
     *
     * @param  string[]  $emails
     * @return string[]
     */
    private function normalizar(array $emails): array
    {
        $out = [];
        foreach ($emails as $e) {
            $e = strtolower(trim((string) $e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[$e] = true;
            }
        }

        return array_keys($out);
    }
}
