<?php

namespace App\Services;

use App\Models\Canal;
use App\Models\CanalMiembro;
use App\Models\Mensaje;
use App\Models\MensajeriaMiembro;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Armado del estado de la mensajería interna.
 *
 * Vive acá y no en los controladores porque /bootstrap (al entrar) y /sync (cada
 * pocos segundos) devuelven la misma foto: si cada uno la armara por su lado,
 * tarde o temprano difieren y el sidebar queda mostrando algo distinto a lo que
 * hay dentro del canal.
 */
class MensajeriaService
{
    /**
     * Canales visibles para el usuario, con su nombre ya resuelto y sus no leídos.
     *
     * Ojo con el nombre de los directos: no está en la tabla, porque depende de
     * quién mira (para mí el canal es "Juan", para Juan es "yo"). Se resuelve acá.
     */
    public function canalesDe(User $user): array
    {
        $uid = (int) $user->id;

        $misMembresias = CanalMiembro::where('user_id', $uid)->get()->keyBy('canal_id');
        $canalIds = $misMembresias->keys()->all();

        if (empty($canalIds)) {
            return [];
        }

        $canales = Canal::whereIn('id', $canalIds)
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id')
            ->get();

        // Todos los miembros de todos mis canales, de un golpe: hace falta para
        // el nombre de los directos y para el contador de gente de los grupos.
        $miembrosPorCanal = CanalMiembro::whereIn('canal_id', $canalIds)
            ->get()
            ->groupBy('canal_id');

        $usuarios = $this->usuariosPorId(
            $miembrosPorCanal->flatten()->pluck('user_id')->unique()->all()
        );

        $noLeidos = $this->noLeidos($uid, $misMembresias);

        $salida = [];

        foreach ($canales as $canal) {
            $miembros = $miembrosPorCanal->get($canal->id, collect());
            $mia = $misMembresias->get($canal->id);

            $contraparte = $canal->esDirecto()
                ? $miembros->first(function ($m) use ($uid) {
                    return (int) $m->user_id !== $uid;
                })
                : null;

            $salida[] = [
                'id'                => $canal->id,
                'tipo'              => $canal->tipo,
                'nombre'            => $this->nombreDeCanal($canal, $miembros, $usuarios, $uid),
                'descripcion'       => $canal->descripcion,
                'archivado'         => (bool) $canal->archivado,
                'ultimo_mensaje_at' => optional($canal->ultimo_mensaje_at)->toIso8601String(),
                'no_leidos'         => (int) ($noLeidos[$canal->id] ?? 0),
                'ultimo_leido_id'   => (int) ($mia->ultimo_leido_mensaje_id ?? 0),
                'silenciado'        => (bool) ($mia->silenciado ?? false),
                'mi_rol'            => $mia->rol ?? 'miembro',
                'supervision'       => false,
                // En un directo, con quién hablo. Lo usa el front para el avatar
                // y el punto de presencia sin tener que cruzar el directorio.
                'contraparte_id'    => $contraparte ? (int) $contraparte->user_id : null,
                'miembros'          => $miembros->pluck('user_id')->map('intval')->values()->all(),
            ];
        }

        return $salida;
    }

    /**
     * Grupos que el admin puede supervisar sin participar de ellos.
     *
     * Van marcados con supervision=true y sin no leídos: no son conversaciones
     * suyas, así que no le generan badges ni le reclaman atención. Los directos
     * jamás aparecen acá (Canal::puedeLeer()).
     */
    public function gruposSupervisables(User $user): array
    {
        if ((int) $user->admin !== 1) {
            return [];
        }

        $propios = CanalMiembro::where('user_id', (int) $user->id)->pluck('canal_id')->all();

        $canales = Canal::where('tipo', 'grupo')
            ->whereNotIn('id', $propios ?: [0])
            ->orderByDesc('ultimo_mensaje_at')
            ->get();

        return $canales->map(function ($canal) {
            return [
                'id'                => $canal->id,
                'tipo'              => 'grupo',
                'nombre'            => $canal->nombre,
                'descripcion'       => $canal->descripcion,
                'archivado'         => (bool) $canal->archivado,
                'ultimo_mensaje_at' => optional($canal->ultimo_mensaje_at)->toIso8601String(),
                'no_leidos'         => 0,
                'ultimo_leido_id'   => 0,
                'silenciado'        => true,
                'mi_rol'            => null,
                'supervision'       => true,
                'contraparte_id'    => null,
                'miembros'          => $canal->miembros()->pluck('user_id')->map('intval')->all(),
            ];
        })->all();
    }

    /**
     * No leídos de todos mis canales en UNA consulta.
     *
     * El cursor es distinto en cada canal, así que se arma un OR por canal en vez
     * de un GROUP BY plano. Con la cantidad de canales que tiene una persona eso
     * es una consulta corta; hacer una por canal serían N viajes a la base cada
     * cuatro segundos, que es justo lo que no puede pagar el polling.
     */
    protected function noLeidos(int $uid, $misMembresias): array
    {
        if ($misMembresias->isEmpty()) {
            return [];
        }

        return DB::table('mensajeria_mensajes')
            ->selectRaw('canal_id, count(*) as n')
            ->whereNull('eliminado_at')
            ->where('user_id', '!=', $uid)
            ->where(function ($q) use ($misMembresias) {
                foreach ($misMembresias as $canalId => $membresia) {
                    $q->orWhere(function ($s) use ($canalId, $membresia) {
                        $s->where('canal_id', $canalId)
                          ->where('id', '>', (int) $membresia->ultimo_leido_mensaje_id);
                    });
                }
            })
            ->groupBy('canal_id')
            ->pluck('n', 'canal_id')
            ->all();
    }

    /**
     * Total de mensajes sin leer, para el badge del navbar.
     *
     * Existe aparte de canalesDe() porque lo consulta el navbar desde cualquier
     * pantalla de la app, y ahí no hace falta nada más que el número: armar la
     * lista completa de canales con nombres, presencia y directorio para mostrar
     * un "3" sería pagar todo eso cada minuto en cada página.
     *
     * Incluye los canales silenciados a propósito: silenciar corta las push, no
     * el badge —es el mismo criterio que el sidebar del módulo—.
     */
    public function totalNoLeidos(User $user): int
    {
        $uid = (int) $user->id;

        $misMembresias = CanalMiembro::where('user_id', $uid)->get()->keyBy('canal_id');

        if ($misMembresias->isEmpty()) {
            return 0;
        }

        return (int) array_sum($this->noLeidos($uid, $misMembresias));
    }

    /**
     * Mensajes nuevos, editados y borrados desde el último sync.
     *
     * Dos condiciones en una sola consulta: `id > desde` trae lo nuevo y
     * `updated_at > desde_ts` trae lo que cambió después de haberse enviado. Sin
     * la segunda, una edición o un borrado sobre un mensaje viejo no llegaría
     * nunca a la gente que ya lo tenía en pantalla.
     *
     * Nota: esto entrega novedades de canales en los que ya estabas. Si te
     * acaban de agregar a un canal con historia, sus mensajes viejos tienen id
     * menor al cursor y no vienen por acá: los carga el front al abrir el canal,
     * que es cuando los necesita.
     */
    public function mensajesDesde(User $user, int $desde, ?string $desdeTs): array
    {
        $canalIds = CanalMiembro::where('user_id', (int) $user->id)->pluck('canal_id')->all();

        if (empty($canalIds)) {
            return [];
        }

        // with('adjuntos'): paraApi() los serializa, y sin esto serían tantas
        // consultas extra como mensajes devueltos, cada cuatro segundos.
        $query = Mensaje::with('adjuntos')->whereIn('canal_id', $canalIds);

        if ($desdeTs) {
            $query->where(function ($q) use ($desde, $desdeTs) {
                $q->where('id', '>', $desde)
                  ->orWhere('updated_at', '>', $desdeTs);
            });
        } else {
            $query->where('id', '>', $desde);
        }

        // Tope duro: si alguien estuvo desconectado mucho tiempo, no le mandamos
        // la conversación entera en el primer sync. El front nota que llegó al
        // tope y recarga el canal que esté mirando.
        return $query->orderBy('id')
            ->limit(300)
            ->get()
            ->map(function ($m) {
                return $m->paraApi();
            })
            ->all();
    }

    /** Directorio del módulo: con quién se puede hablar. */
    public function directorio(): array
    {
        $miembros = MensajeriaMiembro::where('activo', true)->get();
        $usuarios = $this->usuariosPorId($miembros->pluck('user_id')->all());

        return $miembros->map(function ($m) use ($usuarios) {
            $u = $usuarios->get((int) $m->user_id);

            return [
                'user_id'  => (int) $m->user_id,
                'nombre'   => $u->nombreVisible(),
                'email'    => $u->email ?? null,
                'puesto'   => $m->puesto,
                'rol'      => $m->rol,
                'en_linea' => $m->enLinea(),
            ];
        })->sortBy('nombre')->values()->all();
    }

    /**
     * Marca actividad para la presencia.
     *
     * Se escribe como mucho una vez por minuto y no en cada /sync: la presencia
     * se muestra con granularidad de minutos igual, así que un UPDATE cada cuatro
     * segundos por persona conectada sería desgaste sin ninguna diferencia visible.
     */
    public function tocarPresencia(User $user): void
    {
        $miembro = MensajeriaMiembro::where('user_id', (int) $user->id)->first();

        if (! $miembro) {
            return;
        }

        if (! $miembro->ultima_actividad || $miembro->ultima_actividad->lt(now()->subSeconds(60))) {
            $miembro->ultima_actividad = now();
            $miembro->save();
        }
    }

    /**
     * Aviso generado por la app ("X agregó a Y").
     *
     * Sin autor humano: user_id guarda quién lo provocó para poder auditarlo,
     * pero el front lo pinta centrado y en gris, no como un mensaje de esa persona.
     */
    public function mensajeSistema(Canal $canal, int $userId, string $texto): Mensaje
    {
        $mensaje = Mensaje::create([
            'canal_id' => $canal->id,
            'user_id'  => $userId,
            'tipo'     => 'sistema',
            'cuerpo'   => $texto,
        ]);

        $canal->ultimo_mensaje_at = $mensaje->created_at;
        $canal->save();

        return $mensaje;
    }

    /** Usuarios por id, para resolver nombres sin un SELECT por fila. */
    protected function usuariosPorId(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return User::whereIn('id', $ids)->get(['id', 'nombre', 'email'])->keyBy('id');
    }

    /** El nombre que ve ESTE usuario: el del grupo, o el del otro en un directo. */
    protected function nombreDeCanal(Canal $canal, $miembros, $usuarios, int $uid): string
    {
        if ($canal->esGrupo()) {
            return (string) $canal->nombre;
        }

        $otro = $miembros->first(function ($m) use ($uid) {
            return (int) $m->user_id !== $uid;
        });

        if (! $otro) {
            // Directo consigo mismo: el controlador no lo permite crear, pero si
            // quedara uno de una prueba, que no rompa el listado.
            return 'Mis notas';
        }

        $u = $usuarios->get((int) $otro->user_id);

        return $u->nombreVisible();
    }
}
