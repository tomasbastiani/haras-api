<?php

namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\CanalMiembro;
use App\Models\MensajeriaMiembro;
use App\Models\User;
use App\Services\MensajeriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Canales de la mensajería interna: grupos y directos.
 *
 * Todo lo de acá corre detrás de ['auth:sanctum', 'mensajeria'] y el autor sale
 * siempre del token, nunca del body.
 */
class CanalController extends Controller
{
    protected MensajeriaService $mensajeria;

    public function __construct(MensajeriaService $mensajeria)
    {
        $this->mensajeria = $mensajeria;
    }

    /**
     * Crea un grupo.
     *
     * Requiere ser miembro del directorio de verdad: un admin que no participa
     * del módulo puede supervisar grupos, pero no fundar canales en los que no
     * está —quedaría un canal sin dueño que nadie pidió.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! MensajeriaMiembro::esMiembro((int) $user->id)) {
            return response()->json([
                'message' => 'Tu cuenta no está habilitada en la mensajería, sólo podés supervisar grupos.',
            ], 403);
        }

        $datos = $request->validate([
            'nombre'      => 'required|string|min:2|max:80',
            'descripcion' => 'nullable|string|max:255',
            'miembros'    => 'array|max:' . (int) config('mensajeria.grupo_max_miembros'),
            'miembros.*'  => 'integer',
        ]);

        // Sólo gente habilitada y activa. Filtrar en vez de rechazar el request
        // completo: si a alguien le dieron de baja hace un minuto, el que está
        // creando el grupo no tiene por qué enterarse con un error de validación.
        $invitados = $this->miembrosHabilitados($datos['miembros'] ?? []);

        // El creador siempre entra, sin depender de que el front lo mande.
        $invitados = array_values(array_unique(array_merge($invitados, [(int) $user->id])));

        $canal = DB::transaction(function () use ($datos, $invitados, $user) {
            $canal = Canal::create([
                'tipo'        => 'grupo',
                'nombre'      => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'creado_por'  => (int) $user->id,
            ]);

            foreach ($invitados as $userId) {
                CanalMiembro::create([
                    'canal_id' => $canal->id,
                    'user_id'  => $userId,
                    'rol'      => $userId === (int) $user->id ? 'owner' : 'miembro',
                ]);
            }

            $this->mensajeria->mensajeSistema(
                $canal,
                (int) $user->id,
                $user->nombreVisible() . ' creó el canal'
            );

            return $canal;
        });

        return response()->json([
            'message'  => 'Canal creado.',
            'canal_id' => $canal->id,
        ], 201);
    }

    /**
     * Abre el directo con otra persona, o devuelve el que ya existía.
     *
     * Es idempotente a propósito, y esa es toda la razón de ser de
     * `clave_directo`: el front no tiene que saber si la conversación existe ya.
     * Llama siempre acá y recibe el mismo canal.
     */
    public function directo(Request $request)
    {
        $user = $request->user();

        if (! MensajeriaMiembro::esMiembro((int) $user->id)) {
            return response()->json([
                'message' => 'Tu cuenta no está habilitada en la mensajería.',
            ], 403);
        }

        $datos = $request->validate(['user_id' => 'required|integer']);

        $otroId = (int) $datos['user_id'];
        $miId = (int) $user->id;

        if ($otroId === $miId) {
            return response()->json(['message' => 'No podés abrir un chat con vos mismo.'], 422);
        }

        if (! MensajeriaMiembro::esMiembro($otroId)) {
            return response()->json([
                'message' => 'Esa persona no está habilitada en la mensajería interna.',
            ], 422);
        }

        $clave = Canal::claveDirecto($miId, $otroId);

        $canal = Canal::where('clave_directo', $clave)->first();

        if ($canal) {
            return response()->json(['canal_id' => $canal->id, 'creado' => false]);
        }

        try {
            $canal = DB::transaction(function () use ($clave, $miId, $otroId) {
                $canal = Canal::create([
                    'tipo'          => 'directo',
                    'clave_directo' => $clave,
                    'creado_por'    => $miId,
                ]);

                foreach ([$miId, $otroId] as $userId) {
                    CanalMiembro::create([
                        'canal_id' => $canal->id,
                        'user_id'  => $userId,
                        'rol'      => 'miembro',
                    ]);
                }

                return $canal;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Carrera: los dos tocaron "chat" a la vez y el índice único de
            // clave_directo rechazó al segundo. Es el caso que el índice existe
            // para atajar, así que se devuelve el canal del ganador.
            $canal = Canal::where('clave_directo', $clave)->first();

            if (! $canal) {
                throw $e;
            }

            return response()->json(['canal_id' => $canal->id, 'creado' => false]);
        }

        return response()->json(['canal_id' => $canal->id, 'creado' => true], 201);
    }

    /** Detalle del canal + su gente. Para el panel lateral de un grupo. */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($id);

        if (! $canal->puedeLeer($user)) {
            return response()->json(['message' => 'No tenés acceso a esta conversación'], 403);
        }

        $miembros = $canal->miembros()->get();
        $usuarios = User::whereIn('id', $miembros->pluck('user_id')->all())
            ->get(['id', 'nombre', 'email'])
            ->keyBy('id');

        $fichas = MensajeriaMiembro::whereIn('user_id', $miembros->pluck('user_id')->all())
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'id'          => $canal->id,
            'tipo'        => $canal->tipo,
            'nombre'      => $canal->nombre,
            'descripcion' => $canal->descripcion,
            'archivado'   => (bool) $canal->archivado,
            'supervision' => $canal->esSupervision($user),
            'puedo_escribir' => $canal->puedeEscribir($user),
            'miembros'    => $miembros->map(function ($m) use ($usuarios, $fichas) {
                $u = $usuarios->get((int) $m->user_id);
                $ficha = $fichas->get((int) $m->user_id);

                return [
                    'user_id'  => (int) $m->user_id,
                    'nombre'   => $u->nombreVisible(),
                    'rol'      => $m->rol,
                    'puesto'   => $ficha->puesto ?? null,
                    'en_linea' => $ficha ? $ficha->enLinea() : false,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Agrega gente a un grupo.
     *
     * Puede el owner, un moderador del módulo o un admin de la app. Un miembro
     * común no: en un equipo chico, que cualquiera pueda meter a cualquiera en
     * cualquier canal termina en conversaciones con público inesperado.
     */
    public function agregarMiembros(Request $request, $id)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($id);

        if ($canal->esDirecto()) {
            return response()->json([
                'message' => 'Un chat directo no admite más gente. Creá un grupo.',
            ], 422);
        }

        if (! $this->puedeAdministrar($canal, $user)) {
            return response()->json(['message' => 'No podés administrar este canal'], 403);
        }

        $datos = $request->validate([
            'miembros'   => 'required|array|min:1',
            'miembros.*' => 'integer',
        ]);

        $candidatos = $this->miembrosHabilitados($datos['miembros']);
        $yaEstan = $canal->miembros()->pluck('user_id')->map('intval')->all();
        $nuevos = array_values(array_diff($candidatos, $yaEstan));

        if (count($yaEstan) + count($nuevos) > (int) config('mensajeria.grupo_max_miembros')) {
            return response()->json([
                'message' => 'El canal llegó al máximo de integrantes.',
            ], 422);
        }

        if (empty($nuevos)) {
            return response()->json(['message' => 'No hay nadie nuevo para agregar.'], 422);
        }

        $nombres = User::whereIn('id', $nuevos)->pluck('nombre', 'id');

        DB::transaction(function () use ($canal, $nuevos, $user, $nombres) {
            foreach ($nuevos as $userId) {
                CanalMiembro::create([
                    'canal_id' => $canal->id,
                    'user_id'  => $userId,
                    'rol'      => 'miembro',
                ]);
            }

            $this->mensajeria->mensajeSistema(
                $canal,
                (int) $user->id,
                $user->nombreVisible() . ' agregó a ' . implode(', ', $nombres->all())
            );
        });

        return response()->json(['message' => 'Listo, ya están en el canal.']);
    }

    /** Saca a alguien del grupo, o te saca a vos mismo (salir del canal). */
    public function quitarMiembro(Request $request, $id, $userId)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($id);
        $userId = (int) $userId;

        if ($canal->esDirecto()) {
            return response()->json([
                'message' => 'Un chat directo no se abandona. Si no querés notificaciones, silencialo.',
            ], 422);
        }

        // Salir por decisión propia siempre se puede; sacar a otro requiere
        // administrar el canal.
        $esSalidaPropia = $userId === (int) $user->id;

        if (! $esSalidaPropia && ! $this->puedeAdministrar($canal, $user)) {
            return response()->json(['message' => 'No podés administrar este canal'], 403);
        }

        $membresia = CanalMiembro::where('canal_id', $canal->id)->where('user_id', $userId)->first();

        if (! $membresia) {
            return response()->json(['message' => 'Esa persona no está en el canal.'], 422);
        }

        // Sin owner, el canal queda sin nadie que pueda administrarlo salvo un
        // admin. Se pasa el rol al miembro más antiguo antes de dejarlo salir.
        if ($membresia->rol === 'owner') {
            $sucesor = CanalMiembro::where('canal_id', $canal->id)
                ->where('user_id', '!=', $userId)
                ->orderBy('id')
                ->first();

            if ($sucesor) {
                $sucesor->rol = 'owner';
                $sucesor->save();
            }
        }

        $nombre = optional(User::find($userId))->nombreVisible() ?: 'Alguien';

        DB::transaction(function () use ($membresia, $canal, $user, $nombre, $esSalidaPropia) {
            $membresia->delete();

            $this->mensajeria->mensajeSistema(
                $canal,
                (int) $user->id,
                $esSalidaPropia
                    ? $nombre . ' salió del canal'
                    : $user->nombreVisible() . ' sacó a ' . $nombre . ' del canal'
            );
        });

        return response()->json(['message' => 'Hecho.']);
    }

    /** Archiva o desarchiva un grupo. No se borra: su historial es del equipo. */
    public function archivar(Request $request, $id)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($id);

        if ($canal->esDirecto()) {
            return response()->json(['message' => 'Un chat directo no se archiva.'], 422);
        }

        if (! $this->puedeAdministrar($canal, $user)) {
            return response()->json(['message' => 'No podés administrar este canal'], 403);
        }

        $datos = $request->validate(['archivado' => 'required|boolean']);

        $canal->archivado = $datos['archivado'];
        $canal->save();

        $this->mensajeria->mensajeSistema(
            $canal,
            (int) $user->id,
            $user->nombreVisible() . ($datos['archivado'] ? ' archivó el canal' : ' reabrió el canal')
        );

        return response()->json([
            'message' => $datos['archivado'] ? 'Canal archivado.' : 'Canal reabierto.',
        ]);
    }

    /** Silenciar/desilenciar: corta las push, no el acceso. */
    public function silenciar(Request $request, $id)
    {
        $datos = $request->validate(['silenciado' => 'required|boolean']);

        $membresia = CanalMiembro::where('canal_id', $id)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (! $membresia) {
            return response()->json(['message' => 'No participás de esta conversación'], 403);
        }

        $membresia->silenciado = $datos['silenciado'];
        $membresia->save();

        return response()->json(['message' => 'Preferencia guardada.']);
    }

    /** Owner del canal, moderador del módulo o admin de la app. */
    protected function puedeAdministrar(Canal $canal, User $user): bool
    {
        if ((int) $user->admin === 1) {
            return true;
        }

        if (MensajeriaMiembro::esModerador((int) $user->id)) {
            return true;
        }

        return CanalMiembro::where('canal_id', $canal->id)
            ->where('user_id', (int) $user->id)
            ->where('rol', 'owner')
            ->exists();
    }

    /** De una lista de user_id, los que realmente están habilitados y activos. */
    protected function miembrosHabilitados(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return MensajeriaMiembro::whereIn('user_id', array_map('intval', $userIds))
            ->where('activo', true)
            ->pluck('user_id')
            ->map('intval')
            ->all();
    }
}
