<?php

namespace App\Console\Commands;

use App\Models\Canal;
use App\Models\CanalMiembro;
use App\Models\Mensaje;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Manda las push de los mensajes que quedaron sin leer.
 *
 * ── Por qué un comando y no un push al postear ──────────────────────────────
 * FcmService::send() hace un POST HTTP secuencial por CADA token de CADA
 * destinatario, y la cola de este proyecto está en `sync` (hosting compartido,
 * sin worker). Notificar dentro del request de "enviar mensaje" dejaría a quien
 * escribe esperando tantas llamadas HTTP como dispositivos haya en el canal: en
 * un grupo de veinte personas, varios segundos por cada mensaje.
 *
 * ── Por qué el minuto de gracia ────────────────────────────────────────────
 * Es lo que evita la push inútil. Si la persona tenía la app abierta y ya leyó
 * el mensaje, cuando corre el comando su cursor de lectura ya pasó ese mensaje y
 * queda fuera de la tanda. Es el mismo comportamiento que tiene Slack, y la
 * razón por la que no te llega una notificación de algo que acabás de leer.
 *
 * ── Agrupado ───────────────────────────────────────────────────────────────
 * Una push por persona y por canal, no una por mensaje: "Ana · 4 mensajes
 * nuevos". Sin esto, una conversación de diez mensajes serían diez
 * notificaciones, que es la forma más rápida de que alguien silencie la app.
 *
 * No escribe en UserNotification (el centro de notificaciones de la app) a
 * propósito: el chat genera muchísimo más volumen que los avisos de paquetes,
 * reclamos y turnos, y los taparía. El estado de no leídos del propio módulo es
 * además más preciso que un historial de avisos.
 */
class NotificarMensajeria extends Command
{
    protected $signature = 'mensajeria:notificar {--seco : Muestra qué se enviaría, sin enviar ni marcar nada}';

    protected $description = 'Envía las push agrupadas de los mensajes sin leer de la mensajería interna';

    public function handle(): int
    {
        $seco = (bool) $this->option('seco');
        $gracia = (int) config('mensajeria.push_gracia_minutos');
        $limite = now()->subMinutes($gracia);

        // Ventana de 24 h: si el comando estuvo caído (o es la primera corrida
        // después del deploy), no se manda una avalancha de avisos viejos que ya
        // no le sirven a nadie.
        $desde = now()->subDay();

        $mensajes = Mensaje::whereNull('notificado_at')
            ->whereNull('eliminado_at')
            ->where('tipo', '!=', 'sistema')
            ->where('created_at', '<=', $limite)
            ->where('created_at', '>=', $desde)
            ->orderBy('id')
            ->limit(500)
            ->get();

        // Los que quedaron fuera de la ventana se marcan igual: si no, el comando
        // los volvería a mirar en cada corrida para siempre.
        if (! $seco) {
            $vencidos = Mensaje::whereNull('notificado_at')
                ->where('created_at', '<', $desde)
                ->update(['notificado_at' => now()]);

            if ($vencidos) {
                $this->line("Se descartaron {$vencidos} mensaje(s) fuera de la ventana de 24 h.");
            }
        }

        if ($mensajes->isEmpty()) {
            $this->info('No hay mensajes para notificar.');

            return self::SUCCESS;
        }

        $canales = Canal::whereIn('id', $mensajes->pluck('canal_id')->unique())->get()->keyBy('id');
        $autores = User::whereIn('id', $mensajes->pluck('user_id')->unique())
            ->get(['id', 'nombre', 'email'])
            ->keyBy('id');

        // Miembros de todos los canales involucrados, de una sola vez. Consultar
        // por canal dentro del loop serían cientos de consultas por corrida, y
        // esto corre cada minuto.
        $miembrosPorCanal = CanalMiembro::whereIn('canal_id', $canales->keys())
            ->where('silenciado', false)
            ->get()
            ->groupBy('canal_id');

        // Pendientes por destinatario y canal: [user_id][canal_id] => datos
        $pendientes = [];

        foreach ($mensajes as $mensaje) {
            $canal = $canales->get($mensaje->canal_id);

            if (! $canal) {
                continue;
            }

            foreach ($miembrosPorCanal->get($canal->id, collect()) as $miembro) {
                // Al autor no se le notifica lo que él mismo escribió.
                if ((int) $miembro->user_id === (int) $mensaje->user_id) {
                    continue;
                }

                // Ya lo leyó: su cursor pasó este mensaje. Es el chequeo que hace
                // que el minuto de gracia sirva para algo.
                if ((int) $miembro->ultimo_leido_mensaje_id >= $mensaje->id) {
                    continue;
                }

                $uid = (int) $miembro->user_id;
                $cid = (int) $canal->id;

                if (! isset($pendientes[$uid][$cid])) {
                    $pendientes[$uid][$cid] = ['canal' => $canal, 'cantidad' => 0, 'ultimo' => null];
                }

                $pendientes[$uid][$cid]['cantidad']++;
                $pendientes[$uid][$cid]['ultimo'] = $mensaje;
            }
        }

        $enviadas = 0;

        // Nombres de los destinatarios, sólo para que el modo seco sea legible.
        $destinatarios = $seco
            ? User::whereIn('id', array_keys($pendientes))->get(['id', 'nombre', 'email'])->keyBy('id')
            : collect();

        foreach ($pendientes as $userId => $porCanal) {
            $tokens = UserFcmToken::where('user_id', $userId)->pluck('token')->all();

            if ($seco) {
                $u = $destinatarios->get($userId);
                $quien = $u ? $u->nombreVisible() : "user {$userId}";
                $cuantosTokens = count($tokens);

                foreach ($porCanal as $datos) {
                    [$titulo, $cuerpo] = $this->armarAviso($datos, $autores, $userId);
                    $this->line("  → {$quien} ({$cuantosTokens} disp.): [{$titulo}] {$cuerpo}");
                }

                continue;
            }

            if (empty($tokens)) {
                continue;
            }

            foreach ($porCanal as $datos) {
                [$titulo, $cuerpo] = $this->armarAviso($datos, $autores, $userId);

                $ok = FcmService::send($tokens, $titulo, $cuerpo, [
                    // URL propia y no config('app.url'): en este proyecto APP_URL
                    // suele quedar en localhost, y una push que abre localhost no
                    // lleva a ninguna parte.
                    'url'      => config('mensajeria.push_url'),
                    'canal_id' => (string) $datos['canal']->id,
                    'tipo'     => 'mensajeria',
                ]);

                if ($ok) {
                    $enviadas++;
                }
            }
        }

        if ($seco) {
            $cuantos = array_sum(array_map('count', $pendientes));
            $this->info("SECO: {$mensajes->count()} mensaje(s) darían {$cuantos} aviso(s) a " . count($pendientes) . ' persona(s). No se envió ni se marcó nada.');

            return self::SUCCESS;
        }

        Mensaje::whereIn('id', $mensajes->pluck('id'))->update(['notificado_at' => now()]);

        $this->info("Se notificaron {$mensajes->count()} mensaje(s) en {$enviadas} aviso(s).");

        return self::SUCCESS;
    }

    /**
     * Texto del aviso.
     *
     * En un grupo se nombra el canal y el último que habló; en un directo, sólo
     * la persona —repetir su nombre en el título y en el cuerpo no agrega nada.
     *
     * El cuerpo del mensaje NO se incluye: aparecería en la pantalla bloqueada
     * del teléfono, que es exactamente donde el contenido de un chat privado no
     * tiene que estar. Con saber quién escribió y cuántos mensajes hay alcanza
     * para decidir si abrirlo.
     *
     * @return array{0: string, 1: string}
     */
    protected function armarAviso(array $datos, $autores, int $destinatarioId): array
    {
        $canal = $datos['canal'];
        $cantidad = (int) $datos['cantidad'];
        $ultimo = $datos['ultimo'];

        $autor = $autores->get((int) $ultimo->user_id);
        $nombreAutor = $autor ? $autor->nombreVisible() : 'Alguien';

        $plural = $cantidad === 1 ? '1 mensaje nuevo' : "{$cantidad} mensajes nuevos";

        if ($canal->esGrupo()) {
            return ["#{$canal->nombre}", "{$nombreAutor} · {$plural}"];
        }

        return [$nombreAutor, $plural];
    }
}
