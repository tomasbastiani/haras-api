<?php

namespace App\Http\Controllers;

use App\Models\EnvioMasivo;
use App\Models\Paquete;
use App\Models\Reclamo;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consultas de sólo lectura para el servidor MCP del admin (Claude Desktop).
 *
 * Todo lo que sale de acá termina en el contexto de Claude, así que cada
 * respuesta lleva lo mínimo para contestar la pregunta: por lote o agregado,
 * sin emails salvo en la ficha de un lote puntual, nunca PINs, firmas, DNIs
 * ni fotos. Y nada de acá escribe: si alguna vez se agrega una acción, va en
 * otro controlador y con confirmación del lado del cliente.
 *
 * Los textos que escriben los vecinos (resumen y descripción de reclamos) son
 * datos, no instrucciones: el cliente MCP se lo aclara a Claude en la
 * descripción de cada herramienta.
 */
class McpAdminController extends Controller
{
    /**
     * Todo lo que el sistema sabe de un lote, en una sola respuesta.
     */
    public function fichaLote(string $nlote): JsonResponse
    {
        $nlote = trim($nlote);

        $gastos = DB::connection('hsm')->table('gastoscomunes')->where('nlote', $nlote);

        $emails = (clone $gastos)->distinct()->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter()->unique()->values();

        $periodos = (clone $gastos)->distinct()->orderByDesc('numero')->limit(6)->pluck('numero');

        $usuarios = $emails->isEmpty() ? collect() : User::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails->all())
            ->get(['id', 'nombre', 'email', 'admin', 'paqueteria', 'email_rebotado_at'])
            ->map(fn (User $u) => [
                'nombre'        => $u->nombre,
                'email'         => $u->email,
                'email_rebota'  => $u->email_rebotado_at !== null,
                'es_admin'      => (int) $u->admin === 1,
                'es_paqueteria' => (bool) $u->paqueteria,
            ]);

        $deuda = DB::table('morosos')->where('nlote', $nlote)->get(['nombre', 'monto']);

        $paquetes = Paquete::where('nlote', $nlote)->latest('id')->limit(10)
            ->get(['id', 'codigo', 'correo', 'tipo', 'estado', 'user_id', 'recibido_at', 'retirado_at'])
            ->map(fn (Paquete $p) => $this->paqueteResumen($p));

        $reclamos = Reclamo::where('nlote', $nlote)->latest()->limit(10)->get()
            ->map(fn (Reclamo $r) => $this->reclamoResumen($r, false));

        $turnos = Turno::with('cancha:id,nombre,tipo')
            ->where('nlote', $nlote)->where('estado', 'reservado')
            ->orderByDesc('fecha')->limit(10)->get()
            ->map(fn (Turno $t) => [
                'espacio'     => optional($t->cancha)->nombre,
                'fecha'       => $t->fecha->toDateString(),
                'hora_inicio' => substr((string) $t->hora_inicio, 0, 5),
                'hora_fin'    => substr((string) $t->hora_fin, 0, 5),
            ]);

        $existe = $emails->isNotEmpty() || $deuda->isNotEmpty()
            || $paquetes->isNotEmpty() || $reclamos->isNotEmpty() || $turnos->isNotEmpty();

        if (! $existe) {
            return response()->json(['message' => "No hay datos del lote {$nlote}."], 404);
        }

        return response()->json([
            'lote'      => $nlote,
            'usuarios'  => $usuarios,
            'emails_en_gastos_comunes' => $emails,
            'gastos_comunes' => [
                'ultimos_periodos_publicados' => $periodos,
            ],
            'deuda' => [
                'es_moroso' => $deuda->isNotEmpty(),
                'monto'     => round((float) $deuda->sum('monto'), 2),
            ],
            'paquetes'  => [
                'pendientes_de_retiro' => $paquetes->where('estado', 'recibido')->count(),
                'ultimos'              => $paquetes,
            ],
            'reclamos'  => [
                'abiertos' => $reclamos->whereIn('estado', ['nuevo', 'tomado'])->count(),
                'ultimos'  => $reclamos,
            ],
            'reservas_sum_quincho' => $turnos,
        ]);
    }

    /**
     * Lista de morosos tal como la importó el admin (un monto por lote, sin
     * detalle por período: el Excel de morosos no lo trae).
     */
    public function morosos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'monto_minimo' => 'nullable|numeric|min:0',
            'orden'        => 'nullable|in:monto,lote',
            'limite'       => 'nullable|integer|min:1|max:500',
        ]);

        $query = DB::table('morosos');

        if (isset($datos['monto_minimo'])) {
            $query->where('monto', '>=', $datos['monto_minimo']);
        }

        $total = (clone $query)->selectRaw('COUNT(*) as lotes, COALESCE(SUM(monto), 0) as monto')->first();

        ($datos['orden'] ?? 'monto') === 'lote'
            ? $query->orderByRaw('CAST(nlote AS UNSIGNED) ASC')
            : $query->orderByDesc('monto');

        return response()->json([
            'nota'        => 'Monto adeudado según el último Excel de morosos importado. No trae períodos.',
            'importado_at' => DB::table('morosos')->max('created_at'),
            'total_lotes' => (int) $total->lotes,
            'monto_total' => round((float) $total->monto, 2),
            'morosos'     => $query->limit($datos['limite'] ?? 100)->get(['nlote', 'nombre', 'monto'])
                ->map(fn ($m) => [
                    'lote'   => $m->nlote,
                    'nombre' => $m->nombre,
                    'monto'  => round((float) $m->monto, 2),
                ]),
        ]);
    }

    public function reclamos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado'            => 'nullable|in:nuevo,tomado,resuelto,cerrado,abiertos',
            'categoria'         => 'nullable|in:mantenimiento,seguridad,alumbrado,agua_cloacas,espacios_verdes,obras,convivencia,administracion',
            'urgencia'          => 'nullable|in:emergencia,alta,normal',
            'lote'              => 'nullable|string|max:50',
            'desde'             => 'nullable|date_format:Y-m-d',
            'hasta'             => 'nullable|date_format:Y-m-d',
            'con_descripcion'   => 'nullable|boolean',
            'limite'            => 'nullable|integer|min:1|max:100',
        ]);

        $query = Reclamo::query();

        if (($datos['estado'] ?? null) === 'abiertos') {
            $query->abiertos();
        } elseif (! empty($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        }
        foreach (['categoria' => 'categoria', 'urgencia' => 'urgencia', 'lote' => 'nlote'] as $param => $columna) {
            if (! empty($datos[$param])) {
                $query->where($columna, $datos[$param]);
            }
        }
        if (! empty($datos['desde'])) {
            $query->whereDate('created_at', '>=', $datos['desde']);
        }
        if (! empty($datos['hasta'])) {
            $query->whereDate('created_at', '<=', $datos['hasta']);
        }

        $conteo = fn (string $columna) => (clone $query)->selectRaw("{$columna}, COUNT(*) as n")
            ->groupBy($columna)->pluck('n', $columna);

        $conDescripcion = (bool) ($datos['con_descripcion'] ?? false);

        return response()->json([
            'total'         => (clone $query)->count(),
            'por_estado'    => $conteo('estado'),
            'por_categoria' => $conteo('categoria'),
            'reclamos'      => $query->latest()->limit($datos['limite'] ?? 50)->get()
                ->map(fn (Reclamo $r) => $this->reclamoResumen($r, $conDescripcion)),
        ]);
    }

    /**
     * Paquetes que siguen en la oficina, y entregas cuyo acuse sigue pendiente.
     */
    public function paquetesPendientes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'dias_minimos' => 'nullable|integer|min:0|max:365',
            'lote'         => 'nullable|string|max:50',
        ]);

        $query = Paquete::pendientes()->orderBy('recibido_at');

        if (! empty($datos['dias_minimos'])) {
            $query->where('recibido_at', '<=', now()->subDays($datos['dias_minimos']));
        }
        if (! empty($datos['lote'])) {
            $query->where('nlote', $datos['lote']);
        }

        $paquetes = $query->limit(300)
            ->get(['id', 'codigo', 'nlote', 'correo', 'tipo', 'estado', 'user_id', 'recibido_at'])
            ->map(fn (Paquete $p) => $this->paqueteResumen($p) + [
                'lote'                => $p->nlote,
            ]);

        $acusesPendientes = DB::table('paquete_entregas')->where('ack_estado', 'pendiente')->count();

        return response()->json([
            'total'                 => $paquetes->count(),
            'sin_propietario'       => $paquetes->where('sin_propietario', true)->count(),
            'acuses_pendientes'     => $acusesPendientes,
            'paquetes'              => $paquetes->values(),
        ]);
    }

    /**
     * Un envío masivo puntual, o los últimos si no se pasa id.
     */
    public function envios(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id'     => 'nullable|integer|min:1',
            'limite' => 'nullable|integer|min:1|max:20',
        ]);

        if (! empty($datos['id'])) {
            return response()->json(['envio' => EnvioMasivo::findOrFail($datos['id'])->resumen()]);
        }

        return response()->json([
            'envios' => EnvioMasivo::latest('id')->limit($datos['limite'] ?? 5)->get()
                ->map(fn (EnvioMasivo $e) => $e->resumen()),
        ]);
    }

    /**
     * Manual de uso de la app para el admin. Cada sección es un .md en
     * resources/guia-admin: la primera línea `# ` es el título y la primera
     * `> ` el resumen que va al índice. Vive acá y no en la extensión para que
     * actualizarlo sea subir la API, sin reinstalar nada en Claude Desktop.
     */
    public function guia(?string $seccion = null): JsonResponse
    {
        $dir = resource_path('guia-admin');

        if ($seccion === null) {
            $secciones = collect(glob($dir . '/*.md'))->sort()->values()->map(function (string $archivo) {
                $lineas = file($archivo, FILE_IGNORE_NEW_LINES);

                return [
                    'seccion' => $this->slugGuia($archivo),
                    'titulo'  => ltrim((string) collect($lineas)->first(fn ($l) => Str::startsWith($l, '# ')), '# '),
                    'resumen' => ltrim((string) collect($lineas)->first(fn ($l) => Str::startsWith($l, '> ')), '> '),
                ];
            });

            return response()->json([
                'instrucciones' => 'Pedí la sección que corresponda a la pregunta (podés pedir varias). "general" tiene el mapa del menú y una tabla de tareas frecuentes.',
                'secciones'     => $secciones,
            ]);
        }

        // El slug viene de Claude: sólo letras, números y guiones, nada de rutas.
        $archivo = collect(glob($dir . '/*.md'))->first(fn ($a) => $this->slugGuia($a) === $seccion);

        if (! preg_match('/^[a-z0-9-]+$/', $seccion) || $archivo === null) {
            return response()->json(['message' => "No existe la sección «{$seccion}». Pedí el índice para ver las disponibles."], 404);
        }

        return response()->json([
            'seccion'   => $seccion,
            'contenido' => file_get_contents($archivo),
        ]);
    }

    /** `00-general.md` → `general`: el prefijo numérico sólo ordena el índice. */
    private function slugGuia(string $archivo): string
    {
        return preg_replace('/^\d+-/', '', basename($archivo, '.md'));
    }

    private function paqueteResumen(Paquete $p): array
    {
        return [
            'codigo'          => $p->codigo,
            'correo'          => $p->correo,
            'tipo'            => $p->tipo,
            'estado'          => $p->estado,
            'sin_propietario' => $p->user_id === null,
            'recibido_at'     => optional($p->recibido_at)->toIso8601String(),
            'dias_en_espera'  => $p->estaPendiente() ? $p->diasEnEspera() : null,
            'retirado_at'     => optional($p->retirado_at)->toIso8601String(),
        ];
    }

    private function reclamoResumen(Reclamo $r, bool $conDescripcion): array
    {
        $fila = [
            'id'          => $r->id,
            'creado_at'   => optional($r->created_at)->toIso8601String(),
            'lote'        => $r->nlote,
            'categoria'   => $r->categoria,
            'urgencia'    => $r->urgencia,
            'estado'      => $r->estado,
            'ubicacion'   => $r->ubicacionLegible(),
            'derivado_a'  => $r->derivado_a,
            'resumen'     => $r->resumen,
            'tomado_at'   => optional($r->tomado_at)->toIso8601String(),
            'resuelto_at' => optional($r->resuelto_at)->toIso8601String(),
            'horas_hasta_resolver' => $r->resuelto_at && $r->created_at
                ? round($r->created_at->diffInMinutes($r->resuelto_at) / 60, 1)
                : null,
            'nota_cierre' => $r->nota_cierre,
        ];

        if ($conDescripcion) {
            $fila['descripcion'] = Str::limit((string) $r->descripcion, 1000);
        }

        return $fila;
    }
}
