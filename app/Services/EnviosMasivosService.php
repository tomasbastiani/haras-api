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
use Illuminate\Support\Facades\Http;
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
    // Resultados de encolar().
    public const CREADO    = 'creado';
    // Ya hubo un envío de este aviso (terminado): se ofrece "Reenviar".
    public const DUPLICADO = 'duplicado';
    // Hay un envío de este aviso en curso o pausado: hay que reanudarlo o
    // cancelarlo antes de reenviar, si no, los que faltan recibirían dos.
    public const EN_CURSO  = 'en_curso';

    /**
     * Crea el envío y sus destinatarios.
     *
     * `$claveBase` identifica el aviso (p. ej. gastos del período 212). Sin
     * `$reenviar`, un segundo intento devuelve el envío existente. Con
     * `$reenviar` se crea un envío nuevo que SÓLO manda a quienes todavía no
     * lo recibieron: los que ya lo tienen quedan como `ya_recibido`. Así
     * frenar, corregir el Excel y volver a mandar nunca duplica.
     *
     * Desde el arranque quedan `omitido` las direcciones rebotadas: las
     * marcadas en users y las que Postmark tiene bloqueadas (se le pregunta en
     * el momento, porque muchas de la lista de gastos no tienen usuario).
     *
     * @param  string[]  $emails
     * @return array{0: EnvioMasivo, 1: string}  [envío, CREADO|DUPLICADO|EN_CURSO]
     */
    public function encolar(string $tipo, string $claveBase, bool $reenviar, array $emails, array $datos, ?int $userId): array
    {
        $emails = $this->normalizar($emails);

        $anteriores = EnvioMasivo::where('clave', $claveBase)
            ->orWhere('clave', 'like', $claveBase . ':%')
            ->orderByDesc('id')
            ->get();

        if ($enCurso = $anteriores->firstWhere('finalizado_at', null)) {
            return [$enCurso, self::EN_CURSO];
        }
        if ($anteriores->isNotEmpty() && ! $reenviar) {
            return [$anteriores->first(), self::DUPLICADO];
        }

        $clave = $anteriores->isEmpty() ? $claveBase : $claveBase . ':' . now()->format('YmdHis');

        $yaRecibieron = $anteriores->isEmpty() ? [] : array_flip(
            EnvioMasivoDestinatario::whereIn('envio_masivo_id', $anteriores->pluck('id'))
                ->where('estado', EnvioMasivoDestinatario::ENVIADO)
                ->pluck('email')
                ->all()
        );

        $rebotados = array_flip(array_merge(
            User::emailsRebotados($emails),
            $this->bloqueadosEnPostmark()
        ));

        try {
            $envio = DB::transaction(function () use ($tipo, $clave, $emails, $datos, $userId, $rebotados, $yaRecibieron) {
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
                $filas = array_map(function ($email) use ($envio, $ahora, $rebotados, $yaRecibieron) {
                    [$estado, $detalle] = isset($yaRecibieron[$email])
                        ? [EnvioMasivoDestinatario::YA_RECIBIDO, 'Ya lo recibió en un envío anterior']
                        : (isset($rebotados[$email])
                            ? [EnvioMasivoDestinatario::OMITIDO, 'Email rebotado o bloqueado en Postmark']
                            : [EnvioMasivoDestinatario::PENDIENTE, null]);

                    return [
                        'envio_masivo_id' => $envio->id,
                        'email'           => $email,
                        'estado'          => $estado,
                        'detalle'         => $detalle,
                        'created_at'      => $ahora,
                        'updated_at'      => $ahora,
                    ];
                }, $emails);

                foreach (array_chunk($filas, 500) as $tanda) {
                    EnvioMasivoDestinatario::insert($tanda);
                }

                return $envio;
            });
        } catch (QueryException $e) {
            // Dos clics casi simultáneos: el segundo choca contra la clave
            // única. Devolvemos el envío que ganó.
            if ($existente = EnvioMasivo::where('clave', $clave)->first()) {
                return [$existente, self::EN_CURSO];
            }
            throw $e;
        }

        $this->cerrarTerminados([$envio->id]);

        Log::info('Envío masivo encolado', [
            'id' => $envio->id, 'tipo' => $tipo, 'clave' => $clave, 'reenvio' => $anteriores->isNotEmpty(),
            'total' => count($emails), 'por' => $userId,
        ]);

        return [$envio, self::CREADO];
    }

    /**
     * Clave del aviso de gastos comunes: uno por período.
     */
    public function claveGastos(?string $periodo): string
    {
        return 'gastos_comunes:' . ($periodo ?? 'sin-periodo');
    }

    /**
     * Clave del mail personalizado: mismo asunto + cuerpo + destinatarios =
     * mismo aviso. Frena el doble clic y el "reintentar" después de un error
     * de red, que eran los que duplicaban.
     */
    public function clavePersonalizado(string $asunto, string $cuerpo, array $emails): string
    {
        $emails = $this->normalizar($emails);
        sort($emails);

        return 'personalizado:' . sha1($asunto . "\0" . $cuerpo . "\0" . implode(',', $emails));
    }

    /**
     * Pausar / reanudar / cancelar un envío en curso.
     *
     * Pausar no corta la tanda que se está mandando en ese momento (hasta
     * `mail.masivos.por_minuto` mails): frena las siguientes.
     */
    public function cambiarEstado(EnvioMasivo $envio, string $accion): void
    {
        $q = EnvioMasivoDestinatario::where('envio_masivo_id', $envio->id);

        switch ($accion) {
            case 'pausar':
                (clone $q)->where('estado', EnvioMasivoDestinatario::PENDIENTE)
                    ->update(['estado' => EnvioMasivoDestinatario::PAUSADO, 'updated_at' => now()]);
                break;

            case 'reanudar':
                (clone $q)->where('estado', EnvioMasivoDestinatario::PAUSADO)
                    ->update(['estado' => EnvioMasivoDestinatario::PENDIENTE, 'updated_at' => now()]);
                $envio->update(['finalizado_at' => null]);
                break;

            case 'cancelar':
                (clone $q)->whereIn('estado', [EnvioMasivoDestinatario::PENDIENTE, EnvioMasivoDestinatario::PAUSADO])
                    ->update(['estado' => EnvioMasivoDestinatario::CANCELADO, 'detalle' => 'Cancelado por el admin', 'updated_at' => now()]);
                break;

            default:
                throw new \InvalidArgumentException("Acción desconocida: {$accion}");
        }

        $this->cerrarTerminados([$envio->id]);

        Log::info('Envío masivo: ' . $accion, ['id' => $envio->id]);
    }

    /**
     * Direcciones que Postmark tiene bloqueadas (rebote, queja de spam o baja),
     * en los dos streams. Si Postmark no responde se sigue sin ellas: Postmark
     * igual no les manda, esto es para no intentarlo y que el admin las vea.
     *
     * @return string[]
     */
    private function bloqueadosEnPostmark(): array
    {
        $token = config('services.postmark.token');
        if (! $token || config('mail.default') !== 'smtp' || ! str_contains((string) config('mail.mailers.smtp.host'), 'postmark')) {
            return [];
        }

        $out = [];
        foreach (array_unique(array_filter(['outbound', config('mail.stream_masivo')])) as $stream) {
            try {
                $resp = Http::withHeaders([
                    'Accept'                  => 'application/json',
                    'X-Postmark-Server-Token' => $token,
                ])->timeout(10)->get("https://api.postmarkapp.com/message-streams/{$stream}/suppressions/dump");

                foreach ((array) $resp->json('Suppressions') as $s) {
                    if (! empty($s['EmailAddress'])) {
                        $out[] = strtolower(trim($s['EmailAddress']));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo leer la lista de bloqueados de Postmark', ['stream' => $stream, 'error' => $e->getMessage()]);
            }
        }

        return $out;
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
            // Pausado no es terminado: queda abierto hasta reanudar o cancelar.
            $quedan = EnvioMasivoDestinatario::where('envio_masivo_id', $id)
                ->whereIn('estado', [EnvioMasivoDestinatario::PENDIENTE, EnvioMasivoDestinatario::PAUSADO])
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
