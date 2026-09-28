<?php

namespace App\Http\Controllers;

use App\Models\Adjunto;
use App\Models\Canal;
use App\Models\CanalMiembro;
use App\Models\Mensaje;
use App\Models\User;
use App\Services\MensajeriaAdjuntoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mensajes de la mensajería interna.
 *
 * El autor sale SIEMPRE de $request->user() (token de Sanctum). Nunca de un
 * user_id del body: es la convención que sostiene todo el resto de la API y lo
 * que evita que alguien escriba haciéndose pasar por otro.
 */
class MensajeController extends Controller
{
    protected MensajeriaAdjuntoService $adjuntos;

    public function __construct(MensajeriaAdjuntoService $adjuntos)
    {
        $this->adjuntos = $adjuntos;
    }

    /**
     * Hilo de un canal, paginado HACIA ATRÁS.
     *
     * Se pagina por id y no por offset: en una conversación viva, mientras se
     * scrollea hacia arriba entran mensajes nuevos abajo, y un OFFSET se corre
     * con cada uno —el usuario ve mensajes repetidos o se saltea otros. Con
     * `antes=<id>` la ventana es estable.
     */
    public function index(Request $request, $canalId)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($canalId);

        if (! $canal->puedeLeer($user)) {
            return response()->json(['message' => 'No tenés acceso a esta conversación'], 403);
        }

        $datos = $request->validate([
            'antes'  => 'nullable|integer|min:1',
            'limite' => 'nullable|integer|min:1|max:100',
        ]);

        $limite = (int) ($datos['limite'] ?? config('mensajeria.pagina'));

        $query = Mensaje::with('adjuntos')->where('canal_id', $canal->id);

        if (! empty($datos['antes'])) {
            $query->where('id', '<', (int) $datos['antes']);
        }

        // Se pide en orden descendente (los últimos N) y se invierte para
        // devolverlo cronológico, que es como lo pinta el front.
        $mensajes = $query->orderByDesc('id')->limit($limite + 1)->get();

        $hayMas = $mensajes->count() > $limite;
        $mensajes = $mensajes->take($limite)->reverse()->values();

        return response()->json([
            'mensajes' => $mensajes->map(function ($m) {
                return $m->paraApi();
            })->all(),
            'hay_mas'  => $hayMas,
            'autores'  => $this->autoresDe($mensajes),
        ]);
    }

    /**
     * Publica un mensaje.
     *
     * No dispara push acá, y es deliberado: FcmService::send() hace un POST HTTP
     * por token de forma secuencial y la cola está en `sync`, así que notificar a
     * un grupo dentro de este request lo dejaría colgado tantas llamadas HTTP
     * como gente haya en el canal. Las push van a salir agrupadas desde un
     * comando del scheduler (fase 2).
     */
    public function store(Request $request, $canalId)
    {
        $user = $request->user();
        $canal = Canal::findOrFail($canalId);

        if (! $canal->puedeEscribir($user)) {
            // Distinguir los dos casos: el admin que supervisa un grupo ajeno ve
            // por qué no tiene caja de texto, en vez de un "no autorizado" seco.
            $mensaje = $canal->esSupervision($user)
                ? 'Estás viendo este canal como supervisor: podés leerlo pero no escribir en él.'
                : ($canal->archivado
                    ? 'El canal está archivado.'
                    : 'No participás de esta conversación.');

            return response()->json(['message' => $mensaje], 403);
        }

        $maxKb = (int) config('mensajeria.adjuntos.max_kb');
        $mimes = implode(',', (array) config('mensajeria.adjuntos.mimes'));

        $datos = $request->validate([
            // required_without: un mensaje puede ser sólo una foto, sin texto.
            'cuerpo'        => 'required_without:archivos|nullable|string|max:' . (int) config('mensajeria.largo_max'),
            'responde_a_id' => 'nullable|integer',
            'archivos'      => 'nullable|array|max:' . (int) config('mensajeria.adjuntos.max_por_mensaje'),
            'archivos.*'    => 'file|max:' . $maxKb . '|mimes:' . $mimes,
        ], [
            'cuerpo.required_without' => 'Escribí algo o adjuntá un archivo.',
            'cuerpo.max'       => 'El mensaje es demasiado largo (máximo :max caracteres).',
            'archivos.max'     => 'Como máximo :max archivos por mensaje.',
            'archivos.*.max'   => 'Cada archivo puede pesar hasta ' . round($maxKb / 1024) . ' MB.',
            'archivos.*.mimes' => 'Ese tipo de archivo no está permitido. Se aceptan imágenes, PDF y documentos de oficina.',
        ]);

        // El mensaje citado tiene que ser del mismo canal: si no, responder
        // serviría para arrastrar a la vista un mensaje de otra conversación.
        $respondeA = null;
        if (! empty($datos['responde_a_id'])) {
            $respondeA = Mensaje::where('id', (int) $datos['responde_a_id'])
                ->where('canal_id', $canal->id)
                ->value('id');
        }

        $archivos = $request->file('archivos', []);
        $archivos = is_array($archivos) ? $archivos : [$archivos];

        $cuerpo = trim((string) ($datos['cuerpo'] ?? ''));

        if ($cuerpo === '' && empty($archivos)) {
            return response()->json(['message' => 'El mensaje está vacío.'], 422);
        }

        $mensaje = Mensaje::create([
            'canal_id'      => $canal->id,
            'user_id'       => (int) $user->id,
            'tipo'          => empty($archivos) ? 'texto' : 'archivo',
            'cuerpo'        => $cuerpo,
            'responde_a_id' => $respondeA,
        ]);

        // Los archivos se guardan con la fila ya confirmada y fuera de cualquier
        // transacción: un write a disco no se revierte con un rollback (mismo
        // criterio que PaqueteController con la foto del paquete).
        if (! empty($archivos)) {
            $this->adjuntos->guardar($mensaje, $archivos);
            $mensaje->load('adjuntos');
        }

        $canal->ultimo_mensaje_at = $mensaje->created_at;
        $canal->save();

        // Quien escribe ya leyó lo suyo: adelantar su propio cursor evita que el
        // mensaje que acaba de mandar le cuente como no leído en otra pestaña.
        $membresia = CanalMiembro::where('canal_id', $canal->id)
            ->where('user_id', (int) $user->id)
            ->first();

        if ($membresia) {
            $membresia->marcarLeido($mensaje->id);
        }

        return response()->json($mensaje->paraApi(), 201);
    }

    /**
     * Marca hasta dónde leyó el usuario en este canal.
     *
     * Es el endpoint que apaga el badge. Idempotente y sólo hacia adelante (ver
     * CanalMiembro::marcarLeido).
     */
    public function leido(Request $request, $canalId)
    {
        $datos = $request->validate(['mensaje_id' => 'required|integer|min:1']);

        $membresia = CanalMiembro::where('canal_id', $canalId)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        // El admin que supervisa un grupo no tiene membresía y no lleva cursor:
        // no es su conversación, no le corresponde marcarla como leída. No es un
        // error —el front llama a esto al abrir cualquier canal—, así que
        // responde 200 y sigue.
        if (! $membresia) {
            return response()->json(['ultimo_leido_id' => null]);
        }

        $membresia->marcarLeido((int) $datos['mensaje_id']);

        return response()->json(['ultimo_leido_id' => (int) $membresia->ultimo_leido_mensaje_id]);
    }

    /** Edita un mensaje propio. */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $mensaje = Mensaje::findOrFail($id);

        if (! $mensaje->puedeModificar($user)) {
            return response()->json(['message' => 'Sólo podés editar tus propios mensajes'], 403);
        }

        $datos = $request->validate([
            'cuerpo' => 'required|string|max:' . (int) config('mensajeria.largo_max'),
        ], [
            'cuerpo.required' => 'El mensaje no puede quedar vacío. Si querés, borralo.',
            'cuerpo.max'      => 'El mensaje es demasiado largo (máximo :max caracteres).',
        ]);

        $cuerpo = trim($datos['cuerpo']);

        if ($cuerpo === '') {
            return response()->json(['message' => 'El mensaje está vacío.'], 422);
        }

        $mensaje->cuerpo = $cuerpo;
        $mensaje->editado_at = now();
        $mensaje->save();

        return response()->json($mensaje->paraApi());
    }

    /**
     * Borra un mensaje propio dejando lápida.
     *
     * No es un DELETE: el hueco tiene que poder mostrarse como "mensaje
     * eliminado", porque un mensaje que desaparece sin dejar rastro deja la
     * conversación de los demás sin sentido. El cuerpo se vacía en la base, así
     * que el texto no queda ni para quien mire la tabla.
     *
     * Los adjuntos sí se borran de verdad, archivo y fila: una lápida que dejara
     * la foto viva en el disco y servible por su id no sería un borrado, sería un
     * mensaje escondido.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $mensaje = Mensaje::findOrFail($id);

        if (! $mensaje->puedeModificar($user)) {
            return response()->json(['message' => 'Sólo podés borrar tus propios mensajes'], 403);
        }

        $this->adjuntos->borrarDe($mensaje);

        $mensaje->cuerpo = '';
        $mensaje->eliminado_at = now();
        $mensaje->save();

        return response()->json($mensaje->fresh()->load('adjuntos')->paraApi());
    }

    /**
     * Sirve un adjunto.
     *
     * Este endpoint ES el control de acceso de los archivos: viven en el disco
     * `local`, que no se sirve por HTTP, así que no hay ninguna otra puerta. El
     * permiso se resuelve por el canal del mensaje, con la misma regla que los
     * mensajes —o sea que el admin llega a los adjuntos de un grupo y jamás a los
     * de un directo.
     *
     * Mismo criterio que PaqueteController@foto, incluido el `private, no-store`:
     * que no queden cacheados en un proxy compartido ni en el disco del navegador.
     */
    public function adjunto(Request $request, $id)
    {
        $user = $request->user();

        $adjunto = Adjunto::with('mensaje')->findOrFail($id);
        $mensaje = $adjunto->mensaje;

        if (! $mensaje) {
            return response()->json(['message' => 'Adjunto huérfano.'], 404);
        }

        // Un mensaje borrado no tiene adjuntos servibles. En la práctica el
        // archivo ya no existe, pero se corta antes de tocar el disco.
        if ($mensaje->estaEliminado()) {
            return response()->json(['message' => 'El mensaje fue eliminado.'], 404);
        }

        $canal = Canal::find($mensaje->canal_id);

        if (! $canal || ! $canal->puedeLeer($user)) {
            return response()->json(['message' => 'No tenés acceso a este archivo.'], 403);
        }

        if (! Storage::disk('local')->exists($adjunto->path)) {
            return response()->json(['message' => 'El archivo ya no está disponible.'], 404);
        }

        $disposicion = $request->boolean('descarga') || ! $adjunto->esImagen()
            ? 'attachment'
            : 'inline';

        return response(Storage::disk('local')->get($adjunto->path), 200, [
            'Content-Type'        => $adjunto->mime,
            'Content-Disposition' => $disposicion . '; filename="' . $this->nombreParaHeader($adjunto->nombre_original) . '"',
            'Content-Length'      => (string) $adjunto->tamano,
            'Cache-Control'       => 'private, no-store',
            // El archivo lo subió un usuario: que el navegador no se ponga a
            // adivinar el tipo y termine ejecutando como HTML algo que dijimos
            // que era un .txt.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Nombre seguro para el header: sin comillas ni saltos que lo partan. */
    protected function nombreParaHeader(string $nombre): string
    {
        return preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $nombre) ?: 'archivo';
    }

    /**
     * Nombres de los autores del tramo devuelto.
     *
     * Viaja junto al hilo para que el front no tenga que cruzarlo con el
     * directorio: en un canal puede haber mensajes de alguien que ya no está
     * habilitado, y ese nombre no aparece en el directorio activo.
     */
    protected function autoresDe($mensajes): array
    {
        $ids = $mensajes->pluck('user_id')->unique()->all();

        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->get(['id', 'nombre', 'email'])
            ->mapWithKeys(function ($u) {
                return [(int) $u->id => $u->nombreVisible()];
            })
            ->all();
    }
}
