<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\MensajeriaMiembro;
use App\Services\MensajeriaService;
use Illuminate\Http\Request;

/**
 * Estado general de la mensajería interna: entrada al módulo y sincronización.
 *
 * Como en el resto de la app, la identidad sale SIEMPRE de $request->user()
 * (token de Sanctum) y nunca de un email o user_id en el body.
 */
class MensajeriaController extends Controller
{
    protected MensajeriaService $mensajeria;

    public function __construct(MensajeriaService $mensajeria)
    {
        $this->mensajeria = $mensajeria;
    }

    /**
     * Todo lo que el cliente necesita para abrir el módulo, en un solo request.
     *
     * Se junta acá en vez de repartirlo en /canales + /directorio + /config
     * porque esto corre en el arranque, que es cuando el usuario está mirando la
     * pantalla en blanco: tres viajes secuenciales se notan, uno no.
     */
    public function bootstrap(Request $request)
    {
        $user = $request->user();

        $miembro = MensajeriaMiembro::where('user_id', (int) $user->id)->first();
        $esAdmin = (int) $user->admin === 1;

        // Igual que en sync(): antes de leer nada, para no dejar un hueco entre
        // esta foto y el primer sync.
        $ts = now()->toIso8601String();
        $cursor = (int) (Mensaje::max('id') ?? 0);

        $this->mensajeria->tocarPresencia($user);

        $canales = array_merge(
            $this->mensajeria->canalesDe($user),
            $this->mensajeria->gruposSupervisables($user)
        );

        return response()->json([
            'yo' => [
                'user_id' => (int) $user->id,
                'nombre'  => $user->nombreVisible(),
                'email'   => $user->email,
                'puesto'  => $miembro->puesto ?? null,
                'rol'     => $miembro->rol ?? null,
                // Un admin que no está en el directorio entra igual, pero sólo a
                // supervisar grupos: no puede escribir ni abrir conversaciones.
                'miembro' => $miembro && $miembro->activo,
                'admin'   => $esAdmin,
            ],
            'canales'    => $canales,
            'directorio' => $this->mensajeria->directorio(),
            // Cursor global: desde acá arranca el /sync. Es el máximo de la tabla
            // y no el del usuario porque el historial de cada canal lo carga el
            // front al abrirlo; el sync sólo tiene que traer lo que pase después.
            'cursor'      => $cursor,
            'servidor_ts' => $ts,
            'config'     => [
                'sync'      => config('mensajeria.sync'),
                'largo_max' => (int) config('mensajeria.largo_max'),
                'pagina'    => (int) config('mensajeria.pagina'),
                // Los límites de adjuntos los manda el backend para que el front
                // avise antes de subir 8 MB al aire y recibir un 422.
                'adjuntos'  => [
                    'max_kb'          => (int) config('mensajeria.adjuntos.max_kb'),
                    'max_por_mensaje' => (int) config('mensajeria.adjuntos.max_por_mensaje'),
                ],
            ],
        ]);
    }

    /**
     * Latido del tiempo real. Es el endpoint que más se llama de toda la app, así
     * que devuelve TODO el estado de todos los canales de una vez: mensajes
     * nuevos, ediciones, borrados, no leídos y presencia.
     *
     * La alternativa —un request por conversación abierta— multiplicaría la carga
     * por la cantidad de canales de cada persona, y esto corre en hosting
     * compartido.
     */
    public function sync(Request $request)
    {
        $datos = $request->validate([
            'desde'    => 'nullable|integer|min:0',
            'desde_ts' => 'nullable|date',
        ]);

        $user = $request->user();
        $desde = (int) ($datos['desde'] ?? 0);
        $desdeTs = $datos['desde_ts'] ?? null;

        // Se toma ANTES de consultar, no al armar la respuesta: si se tomara
        // después, una edición ocurrida entre la consulta y el return quedaría
        // por debajo del `desde_ts` del próximo sync y no llegaría nunca.
        // Tomarlo antes puede reenviar algo ya entregado, que es inofensivo
        // porque el cliente mergea por id.
        $ts = now()->toIso8601String();

        $this->mensajeria->tocarPresencia($user);

        $mensajes = $this->mensajeria->mensajesDesde($user, $desde, $desdeTs);

        $canales = array_merge(
            $this->mensajeria->canalesDe($user),
            $this->mensajeria->gruposSupervisables($user)
        );

        // El cursor nuevo es el mayor id que el cliente ya vio. Si no vino nada,
        // se queda donde estaba: nunca se adelanta sobre mensajes no entregados.
        $cursor = $desde;
        foreach ($mensajes as $m) {
            if ($m['id'] > $cursor) {
                $cursor = $m['id'];
            }
        }

        return response()->json([
            'mensajes'    => $mensajes,
            'canales'     => $canales,
            'directorio'  => $this->mensajeria->directorio(),
            'cursor'      => $cursor,
            'servidor_ts' => $ts,
        ]);
    }

    /** Gente con la que se puede abrir una conversación. */
    public function directorio(Request $request)
    {
        return response()->json($this->mensajeria->directorio());
    }

    /**
     * Sólo el total de mensajes sin leer, para el badge del navbar.
     *
     * Es el endpoint más liviano del módulo a propósito: lo consulta el navbar
     * desde cualquier pantalla de la app, cada minuto. Devolver acá la foto
     * completa de /bootstrap sería pagar el armado de canales, directorio y
     * presencia en todas las páginas del portal para mostrar un número.
     */
    public function noLeidos(Request $request)
    {
        return response()->json([
            'total' => $this->mensajeria->totalNoLeidos($request->user()),
        ]);
    }

    /**
     * ¿Tiene acceso al módulo? Responde sí o no, nunca 403.
     *
     * Va FUERA del middleware `mensajeria` a propósito, y es el equivalente de
     * /paqueteria/acceso. Hace falta porque las sesiones de esta app no expiran:
     * el flag del login queda congelado en localStorage, así que a quien ya
     * estaba logueado cuando se habilitó el chat nunca le aparecería el módulo.
     * Con esto el front lo pregunta una vez por sesión y se sincroniza.
     *
     * Devolver 200 con acceso=false en lugar de 403 es deliberado: esto lo llama
     * el armado del menú para CUALQUIER usuario, y un 403 ahí ensuciaría la
     * consola de toda la app con un error que no es un error.
     */
    public function acceso(Request $request)
    {
        $user = $request->user();

        $miembro = MensajeriaMiembro::where('user_id', (int) $user->id)
            ->where('activo', true)
            ->exists();

        $esAdmin = (int) $user->admin === 1;

        return response()->json([
            'acceso'  => $miembro || $esAdmin,
            'miembro' => $miembro,
            'admin'   => $esAdmin,
            // Cada cuánto refresca el badge del navbar. Viaja acá porque este
            // request ya lo hace el armado del menú: así el intervalo se ajusta
            // desde el .env sin recompilar la PWA y sin un request extra.
            'badge_ms' => (int) config('mensajeria.badge_ms'),
        ]);
    }
}
