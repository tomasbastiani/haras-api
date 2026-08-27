<?php

namespace App\Http\Controllers;

use App\Models\Paquete;
use App\Models\PaqueteEntrega;
use App\Models\PaqueteEvento;
use App\Models\PaqueteriaOperario;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaqueteController extends Controller
{
    // =====================================================================
    // Vecino
    // =====================================================================

    /**
     * Los paquetes del propio vecino. Incluye el PIN de los que siguen en la
     * oficina: si el push se perdió o borró la notificación, tiene que poder
     * consultarlo desde la app sin llamar a nadie.
     *
     * Matchea por titular congelado O por lote: un lote puede tener más de un
     * propietario (matrimonios, condóminos) y todos tienen que ver el paquete.
     */
    public function mios(Request $request)
    {
        $user = $request->user();
        $lotes = $this->lotesDe($user);

        $paquetes = Paquete::where(function ($q) use ($user, $lotes) {
                $q->where('user_id', $user->id);

                if (! empty($lotes)) {
                    $q->orWhereIn('nlote', $lotes);
                }
            })
            ->with('entrega')
            ->latest('id')
            ->limit(50)
            ->get();

        $paquetes->each(function (Paquete $paquete) {
            if ($paquete->estaPendiente()) {
                $paquete->makeVisible('pin');
            }
        });

        return response()->json($paquetes);
    }

    /** Detalle con el timeline sellado. */
    public function show(Request $request, $id)
    {
        $paquete = Paquete::with(['entrega', 'user:id,nombre,email'])->findOrFail($id);
        $user = $request->user();

        $esTitular = $this->esDestinatario($user, $paquete);

        if (! $esTitular && ! $this->puedeOperar($user)) {
            return response()->json(['message' => 'No tenés acceso a este paquete.'], 403);
        }

        if ($esTitular && $paquete->estaPendiente()) {
            $paquete->makeVisible('pin');
        }

        return response()->json([
            'paquete' => $paquete,
            'eventos' => $paquete->eventos()->with('user:id,nombre')->get()->map(fn ($e) => [
                'id'         => $e->id,
                'tipo'       => $e->tipo,
                'nota'       => $e->nota,
                'por'        => $e->user?->nombre,
                'datos'      => $e->datos(),
                'created_at' => $e->created_at,
            ]),
        ]);
    }

    /**
     * Acuse de recibo del titular. Es la pieza más fuerte de la constancia:
     * la emite el dispositivo autenticado del propietario, no la oficina.
     */
    public function confirmar(Request $request, $id)
    {
        return $this->registrarAcuse($request, $id, 'confirmado');
    }

    /**
     * El titular desconoce la entrega. No es un error del usuario: es una
     * alerta que tiene que llegarle a administración el mismo día.
     */
    public function desconocer(Request $request, $id)
    {
        return $this->registrarAcuse($request, $id, 'desconocido');
    }

    private function registrarAcuse(Request $request, $id, string $resultado)
    {
        $request->validate(['nota' => 'nullable|string|max:500']);

        $paquete = Paquete::with('entrega')->findOrFail($id);
        $user = $request->user();

        if (! $this->esDestinatario($user, $paquete)) {
            return response()->json(['message' => 'Sólo un propietario del lote puede acusar recibo.'], 403);
        }

        $entrega = $paquete->entrega;

        if (! $entrega) {
            return response()->json(['message' => 'Este paquete todavía no fue entregado.'], 409);
        }

        if (! $entrega->ackPendiente()) {
            return response()->json([
                'message' => 'Este paquete ya tiene acuse registrado.',
                'ack'     => $entrega->ack_estado,
            ], 409);
        }

        $entrega->update([
            'ack_estado'  => $resultado,
            'ack_at'      => now(),
            'ack_user_id' => $user->id,
            'ack_ip'      => $request->ip(),
        ]);

        PaqueteEvento::registrar(
            $paquete->id,
            $resultado === 'confirmado' ? 'ack_confirmado' : 'ack_desconocido',
            $request->nota,
            $user->id,
            [
                'folio' => $entrega->folio,
                'ip'    => $request->ip(),
            ]
        );

        if ($resultado === 'desconocido') {
            $this->alertarDesconocimiento($paquete, $entrega, $request->nota);
        }

        return response()->json([
            'message' => $resultado === 'confirmado'
                ? 'Recepción confirmada. Gracias.'
                : 'Registramos que desconocés esta entrega. Administración fue notificada.',
            'entrega' => $entrega->fresh(),
        ]);
    }

    // =====================================================================
    // Operario de paquetería
    // =====================================================================

    /**
     * Si el usuario puede operar la paquetería. Lo usa el menú de la PWA para
     * mostrar u ocultar la sección de la oficina; el permiso real lo sigue
     * chequeando cada endpoint.
     */
    public function acceso(Request $request)
    {
        return response()->json(['operario' => $this->puedeOperar($request->user())]);
    }

    /** Bandeja de la oficina. */
    public function index(Request $request)
    {
        if (! $this->puedeOperar($request->user())) {
            return response()->json(['message' => 'No tenés acceso a la paquetería.'], 403);
        }

        $query = Paquete::with(['entrega', 'user:id,nombre,email'])->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('nlote')) {
            $query->where('nlote', $request->nlote);
        }

        if ($request->boolean('sin_propietario')) {
            $query->sinPropietario();
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('destinatario', 'like', "%{$q}%")
                    ->orWhere('tracking', 'like', "%{$q}%")
                    ->orWhere('nlote', $q);
            });
        }

        return response()->json($query->limit(200)->get());
    }

    /**
     * Alta de un paquete que llega a la oficina.
     *
     * Si el lote no resuelve a un usuario, el paquete se crea igual: existe
     * físicamente y el sistema tiene que reflejarlo. Queda con user_id nulo y
     * visible en el filtro "sin propietario" para corregir el dato.
     */
    public function store(Request $request)
    {
        if (! $this->puedeOperar($request->user())) {
            return response()->json(['message' => 'No tenés acceso a la paquetería.'], 403);
        }

        $datos = $request->validate([
            'nlote'         => 'required|string|max:100',
            'destinatario'  => 'nullable|string|max:150',
            'correo'        => 'required|in:mercadolibre,andreani,oca,correo_argentino,urbano,otro',
            'tracking'      => 'nullable|string|max:100',
            'tipo'          => 'required|in:sobre,caja_chica,caja_grande,bulto',
            'ubicacion'     => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        [$destinatarios, $email] = $this->resolverDestinatarios($datos['nlote']);

        // Titular "principal": el primero que resolvió. Es el que queda
        // congelado en el paquete para el acta; los demás propietarios del lote
        // igual ven el paquete y pueden acusar recibo.
        $userId = $destinatarios->first()?->id;

        $pin = Paquete::generarPin();

        $paquete = DB::transaction(function () use ($datos, $userId, $email, $pin, $request) {
            $paquete = Paquete::create($datos + [
                'codigo'        => Paquete::generarCodigo(),
                'pin'           => $pin,
                'user_id'       => $userId,
                'email_destino' => $email,
                'estado'        => 'recibido',
                'recibido_por'  => $request->user()->id,
                'recibido_at'   => now(),
            ]);

            // El PIN no entra al payload sellado: la bitácora es auditable por
            // administración y no tiene por qué exponer el secreto de retiro.
            PaqueteEvento::registrar(
                $paquete->id,
                'ingreso',
                "Ingresó a paquetería ({$paquete->correo})",
                $request->user()->id,
                [
                    'codigo'    => $paquete->codigo,
                    'nlote'     => $paquete->nlote,
                    'tipo'      => $paquete->tipo,
                    'tracking'  => $paquete->tracking,
                    'ubicacion' => $paquete->ubicacion,
                ]
            );

            return $paquete;
        });

        if ($userId) {
            $this->notificarLlegada($paquete, $pin);
        }

        $cuantos = $destinatarios->count();

        return response()->json([
            'message'         => $cuantos === 0
                ? 'Paquete registrado, pero el lote no tiene propietario vinculado: no se pudo enviar el PIN.'
                : ($cuantos === 1
                    ? 'Paquete registrado. Se le avisó al propietario con su PIN de retiro.'
                    : "Paquete registrado. Se le avisó a los {$cuantos} propietarios del lote con el PIN de retiro."),
            'paquete'         => $paquete->fresh(),
            'sin_propietario' => $cuantos === 0,
            'avisados'        => $cuantos,
        ], 201);
    }

    /**
     * Cierra la entrega y labra el acta.
     *
     * El PIN acá confirma, no busca: el operario ya identificó el paquete por
     * lote o nombre y lo tiene en la mano. Si el PIN fuera la clave de búsqueda,
     * se podrían enumerar paquetes ajenos probando números.
     */
    public function entregar(Request $request, $id)
    {
        if (! $this->puedeOperar($request->user())) {
            return response()->json(['message' => 'No tenés acceso a la paquetería.'], 403);
        }

        $datos = $request->validate([
            'metodo'        => 'required|in:pin,manual',
            'pin'           => 'required_if:metodo,pin|nullable|string|max:10',
            'motivo_manual' => 'required_if:metodo,manual|nullable|string|max:300',
            'retirado_por'  => 'required|in:titular,autorizado,otro',
            'nombre'        => 'required|string|max:150',
            'dni'           => 'nullable|string|max:20',
            'firma'         => 'required|string',
        ]);

        $paquete = Paquete::findOrFail($id);

        if ($paquete->estado !== 'recibido') {
            return response()->json([
                'message' => "El paquete ya figura como {$paquete->estado}.",
            ], 409);
        }

        if ($datos['metodo'] === 'pin') {
            if ($paquete->pinBloqueado()) {
                return response()->json([
                    'message' => 'El PIN está bloqueado por intentos fallidos. Cerrá la entrega por método manual dejando constancia del motivo.',
                ], 423);
            }

            if (! $paquete->verificarPin($datos['pin'] ?? '')) {
                return $this->registrarPinFallido($paquete, $request->user()->id);
            }
        }

        $firmaPath = $this->guardarFirma($paquete->id, $datos['firma']);

        $entrega = DB::transaction(function () use ($paquete, $datos, $firmaPath, $request) {
            $entrega = PaqueteEntrega::create([
                'paquete_id'    => $paquete->id,
                'folio'         => 'PENDIENTE',
                'metodo'        => $datos['metodo'],
                'motivo_manual' => $datos['motivo_manual'] ?? null,
                'retirado_por'  => $datos['retirado_por'],
                'nombre'        => $datos['nombre'],
                'dni'           => $datos['dni'] ?? null,
                'firma_path'    => $firmaPath,
                'operario_id'   => $request->user()->id,
                'entregado_at'  => now(),
                // Sin titular vinculado no hay a quién pedirle acuse. Se marca
                // así de entrada en vez de dejarlo pendiente para siempre.
                'ack_estado'    => $paquete->user_id ? 'pendiente' : 'tacito',
            ]);

            $entrega->update(['folio' => PaqueteEntrega::generarFolio($entrega->id)]);

            $paquete->update([
                'estado'      => 'retirado',
                'retirado_at' => now(),
            ]);

            PaqueteEvento::registrar(
                $paquete->id,
                'entregado',
                "Entregado a {$entrega->nombre}",
                $request->user()->id,
                [
                    'folio'         => $entrega->folio,
                    'metodo'        => $entrega->metodo,
                    'motivo_manual' => $entrega->motivo_manual,
                    'retirado_por'  => $entrega->retirado_por,
                    'nombre'        => $entrega->nombre,
                    'dni'           => $entrega->dni,
                    'firma_sha256'  => hash('sha256', Storage::disk('local')->get($entrega->firma_path)),
                ]
            );

            return $entrega;
        });

        if ($paquete->user_id) {
            $this->pedirAcuse($paquete->fresh(), $entrega);
        }

        return response()->json([
            'message' => 'Entrega registrada. Acta ' . $entrega->folio . '.',
            'entrega' => $entrega,
        ]);
    }

    /** El correo se lleva el paquete de vuelta. */
    public function devolver(Request $request, $id)
    {
        if (! $this->puedeOperar($request->user())) {
            return response()->json(['message' => 'No tenés acceso a la paquetería.'], 403);
        }

        $request->validate(['motivo' => 'required|string|max:300']);

        $paquete = Paquete::findOrFail($id);

        if ($paquete->estado !== 'recibido') {
            return response()->json(['message' => "El paquete ya figura como {$paquete->estado}."], 409);
        }

        $paquete->update(['estado' => 'devuelto']);

        PaqueteEvento::registrar(
            $paquete->id,
            'devuelto',
            $request->motivo,
            $request->user()->id
        );

        $this->avisarAlTitular(
            $paquete,
            "Paquete {$paquete->codigo} devuelto al correo",
            $request->motivo
        );

        return response()->json(['message' => 'Devolución registrada.', 'paquete' => $paquete->fresh()]);
    }

    /**
     * Sirve la firma del acta. Va por acá y no por disco público porque es un
     * dato personal: sólo el titular, el operario y un admin pueden verla.
     */
    public function firma(Request $request, $id)
    {
        $paquete = Paquete::with('entrega')->findOrFail($id);
        $user = $request->user();

        if ($paquete->user_id !== $user->id && ! $this->puedeOperar($user)) {
            return response()->json(['message' => 'No tenés acceso a este acta.'], 403);
        }

        $path = $paquete->entrega?->firma_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'No hay firma registrada.'], 404);
        }

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // =====================================================================
    // Internos
    // =====================================================================

    private function puedeOperar($user): bool
    {
        return (bool) $user->admin || PaqueteriaOperario::esOperario($user->id);
    }

    /**
     * Emails asociados a un lote en gastoscomunes, que es la única fuente que
     * relaciona lote con propietario en este sistema.
     */
    private function emailsDelLote(string $nlote)
    {
        return DB::table('gastoscomunes')
            ->where('nlote', $nlote)
            ->distinct()
            ->pluck('email')
            ->filter()
            ->values();
    }

    /** Lotes de los que este usuario figura como propietario. */
    private function lotesDe($user): array
    {
        return DB::table('gastoscomunes')
            ->where('email', $user->email)
            ->distinct()
            ->pluck('nlote')
            ->all();
    }

    /**
     * Resuelve lote → propietarios a través de gastoscomunes.
     *
     * Devuelve TODOS los usuarios del lote, no el primero: un lote puede tener
     * más de un propietario cargado y avisarle a uno solo dejaría al otro sin
     * enterarse de que le llegó un paquete.
     *
     * Ojo: gastoscomunes tiene emails con basura pegada (por ejemplo
     * 'fulano@gmail.com1233'), así que el match puede fallar legítimamente. En
     * ese caso devolvemos el email crudo igual, para que la bandeja muestre qué
     * dato hay que corregir en vez de dejar el campo vacío.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ?string}
     */
    private function resolverDestinatarios(string $nlote): array
    {
        $emails = $this->emailsDelLote($nlote);
        $users = $emails->isEmpty()
            ? collect()
            : User::whereIn('email', $emails)->get();

        return [$users, $users->first()->email ?? $emails->first()];
    }

    /**
     * Si este usuario es propietario del paquete. Contempla los dos caminos:
     * el titular congelado al ingresar, y cualquier propietario actual del lote
     * (por si el vínculo en gastoscomunes cambió después).
     */
    private function esDestinatario($user, Paquete $paquete): bool
    {
        return $paquete->user_id === $user->id
            || $this->emailsDelLote($paquete->nlote)->contains($user->email);
    }

    /** Todos los usuarios a los que hay que avisarles por este paquete. */
    private function destinatariosDe(Paquete $paquete)
    {
        [$users] = $this->resolverDestinatarios($paquete->nlote);

        // El titular congelado sigue contando aunque el lote haya cambiado de
        // manos entre el ingreso del paquete y hoy: es a quien le llegó el PIN.
        if ($paquete->user_id && ! $users->contains('id', $paquete->user_id)) {
            if ($titular = User::find($paquete->user_id)) {
                $users->push($titular);
            }
        }

        return $users;
    }

    private function registrarPinFallido(Paquete $paquete, int $operarioId)
    {
        $paquete->increment('pin_intentos');
        $paquete->refresh();

        $maximo = (int) config('paqueteria.pin_intentos_max');
        $bloqueado = $paquete->pin_intentos >= $maximo;

        if ($bloqueado) {
            $paquete->update(['pin_bloqueado_at' => now()]);
        }

        PaqueteEvento::registrar(
            $paquete->id,
            'pin_fallido',
            $bloqueado ? 'PIN bloqueado por intentos fallidos' : 'Intento de PIN incorrecto',
            $operarioId,
            ['intentos' => $paquete->pin_intentos]
        );

        return response()->json([
            'message'   => $bloqueado
                ? 'PIN incorrecto. Quedó bloqueado por superar los intentos permitidos: cerrá la entrega por método manual.'
                : 'PIN incorrecto.',
            'intentos'  => $paquete->pin_intentos,
            'restantes' => max(0, $maximo - $paquete->pin_intentos),
            'bloqueado' => $bloqueado,
        ], 422);
    }

    /**
     * Decodifica la firma del canvas y la guarda en disco privado.
     * Valida los magic bytes del PNG, no sólo el prefijo del data URL: el
     * cliente puede mentir en el encabezado.
     */
    private function guardarFirma(int $paqueteId, string $dataUrl): string
    {
        $prefijo = 'data:image/png;base64,';

        if (! str_starts_with($dataUrl, $prefijo)) {
            throw ValidationException::withMessages([
                'firma' => 'La firma debe ser un PNG en base64.',
            ]);
        }

        $binario = base64_decode(substr($dataUrl, strlen($prefijo)), true);

        if ($binario === false || ! str_starts_with($binario, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages([
                'firma' => 'La firma no es una imagen PNG válida.',
            ]);
        }

        if (strlen($binario) > 512 * 1024) {
            throw ValidationException::withMessages([
                'firma' => 'La firma supera el tamaño máximo permitido.',
            ]);
        }

        $path = "paqueteria/firmas/{$paqueteId}.png";
        Storage::disk('local')->put($path, $binario);

        return $path;
    }

    private function notificarLlegada(Paquete $paquete, string $pin): void
    {
        $ubicacion = $paquete->ubicacion ? " Está en {$paquete->ubicacion}." : '';

        $this->avisarAlTitular(
            $paquete,
            "📦 Llegó un paquete para el lote {$paquete->nlote}",
            "Tu PIN de retiro es {$pin}. Presentalo en la oficina de paquetería para retirarlo.{$ubicacion}"
        );

        $paquete->update(['notificado_at' => now()]);

        PaqueteEvento::registrar(
            $paquete->id,
            'notificado',
            'Se notificó al propietario con el PIN de retiro',
            null,
            ['via' => 'push']
        );
    }

    private function pedirAcuse(Paquete $paquete, PaqueteEntrega $entrega): void
    {
        $cuando = $entrega->entregado_at->format('d/m/Y H:i');

        $this->avisarAlTitular(
            $paquete,
            "✅ Se entregó tu paquete {$paquete->codigo}",
            "Retirado por {$entrega->nombre} el {$cuando}. Si no reconocés esta entrega, avisanos desde la app."
        );
    }

    private function alertarDesconocimiento(Paquete $paquete, PaqueteEntrega $entrega, ?string $nota): void
    {
        try {
            $admins = User::where('admin', 1)->get();

            $titulo = "⚠️ Entrega desconocida — acta {$entrega->folio}";
            $cuerpo = "El titular del lote {$paquete->nlote} desconoce la entrega del paquete {$paquete->codigo}"
                . ($nota ? ": {$nota}" : '.');

            foreach ($admins as $admin) {
                $tokens = $admin->fcmTokens()->pluck('token')->toArray();

                if (! empty($tokens)) {
                    FcmService::send($tokens, $titulo, $cuerpo);
                }

                UserNotification::create([
                    'user_id' => $admin->id,
                    'title'   => $titulo,
                    'body'    => $cuerpo,
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Paquetería: error alertando desconocimiento de entrega: ' . $e->getMessage());
        }
    }

    /**
     * Avisa a todos los propietarios del lote. Mismo criterio que
     * avisarAlVecino en ReclamoController, pero a varios destinatarios.
     */
    private function avisarAlTitular(Paquete $paquete, string $titulo, string $cuerpo): void
    {
        try {
            foreach ($this->destinatariosDe($paquete) as $titular) {
                $tokens = $titular->fcmTokens()->pluck('token')->toArray();

                if (! empty($tokens)) {
                    FcmService::send($tokens, $titulo, $cuerpo);
                }

                UserNotification::create([
                    'user_id' => $titular->id,
                    'title'   => $titulo,
                    'body'    => $cuerpo,
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Paquetería: error notificando al titular: ' . $e->getMessage());
        }
    }
}
