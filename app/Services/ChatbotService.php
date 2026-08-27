<?php

namespace App\Services;

use App\Models\ChatConversacion;
use App\Models\Reclamo;
use App\Models\ReclamoRuteo;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ChatbotService
{
    /** Tope de vueltas del loop de tools, para que un modelo confundido no cicle. */
    private const MAX_ITERACIONES = 4;

    public function __construct(private ClaudeService $claude)
    {
    }

    /**
     * Procesa un mensaje del vecino y devuelve la respuesta del bot.
     *
     * @return array{respuesta: string, reclamo: ?Reclamo}
     */
    public function responder(User $user, ChatConversacion $conversacion, string $mensaje): array
    {
        $conversacion->agregar('user', $this->primerTurno($conversacion, $user, $mensaje));

        $reclamoCreado = null;

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $respuesta = $this->claude->mensaje(
                $this->systemPrompt(),
                $conversacion->hilo(),
                $this->tools()
            );

            if ($this->claude->fueRechazado($respuesta)) {
                Log::warning('Claude rechazó el pedido', ['user_id' => $user->id]);

                return [
                    'respuesta' => 'No pude procesar ese mensaje. Si es urgente, escribinos desde el formulario de contacto de la app.',
                    'reclamo'   => null,
                ];
            }

            // El contenido se reenvía tal cual viene (incluye thinking blocks,
            // que deben viajar sin modificar en los turnos siguientes).
            $conversacion->agregar('assistant', $respuesta['content'] ?? []);

            $toolUse = $this->claude->toolUse($respuesta);

            if (! $toolUse) {
                $conversacion->save();

                return ['respuesta' => $this->claude->texto($respuesta), 'reclamo' => null];
            }

            $resultado = $this->ejecutarTool($user, $toolUse);
            $reclamoCreado = $resultado['reclamo'] ?? $reclamoCreado;

            $conversacion->agregar('user', [[
                'type'        => 'tool_result',
                'tool_use_id' => $toolUse['id'],
                'content'     => $resultado['mensaje'],
                'is_error'    => ! empty($resultado['error']),
            ]]);
        }

        if ($reclamoCreado) {
            $conversacion->reclamo_id = $reclamoCreado->id;
            $conversacion->estado = 'cerrada';
        }

        $conversacion->save();

        return [
            'respuesta' => $reclamoCreado
                ? "Listo ✅ Registré tu reclamo #{$reclamoCreado->id} y lo derivé a {$reclamoCreado->derivado_a}."
                : 'Se me complicó procesar eso. ¿Podés contarme de nuevo qué pasó?',
            'reclamo' => $reclamoCreado,
        ];
    }

    /**
     * El contexto del vecino va en el primer mensaje de usuario, NO en el system
     * prompt. Así el system prompt es byte a byte idéntico para todos los
     * usuarios y el prompt caching sirve a toda la comunidad, no a uno solo.
     */
    private function primerTurno(ChatConversacion $conversacion, User $user, string $mensaje)
    {
        if (! empty($conversacion->hilo())) {
            return $mensaje;
        }

        $lotes = $this->lotesDe($user->email);
        $nombre = trim($user->nombre) !== '' ? $user->nombre : explode('@', $user->email)[0];

        $contexto = "[Datos del propietario — no los repitas salvo que hagan falta]\n"
            . "Nombre: {$nombre}\n"
            . 'Lote(s): ' . ($lotes ? implode(', ', $lotes) : 'sin lote asociado') . "\n"
            . 'Fecha y hora: ' . now()->format('d/m/Y H:i') . "\n\n"
            . "[Mensaje]\n" . $mensaje;

        return $contexto;
    }

    private function systemPrompt(): array
    {
        $telefono = config('services.reclamos.telefono_guardia', 'la guardia');

        $texto = <<<PROMPT
        Sos el asistente de reclamos del barrio privado Haras Santa María. Tu trabajo
        es tomar el reclamo de un propietario, entenderlo en pocas preguntas y
        derivarlo al área que corresponde.

        # Cómo trabajás

        Hacé dos o tres preguntas como máximo. Si el vecino ya te dio en su primer
        mensaje lo que necesitás (qué pasa, dónde, hace cuánto), no preguntes nada y
        pasá directo a confirmar. Una pregunta por mensaje: no encadenes tres juntas.

        Antes de registrar nada, mostrale un resumen y pedile que confirme:

            📋 Resumen del reclamo
            Categoría: Alumbrado público
            Ubicación: Vereda, esquina del lote 47
            Urgencia: Normal

            ¿Lo envío a Mantenimiento?

        Recién cuando el vecino confirma, usás la herramienta crear_reclamo. Si dice
        que algo está mal, corregilo y volvé a mostrar el resumen.

        # Cómo clasificás

        - mantenimiento: portones, barreras, bombas, pileta, calles, cordones, SUM.
        - seguridad: garita, cámaras, rondas, ingresos, gente ajena al barrio.
        - alumbrado: luminarias de calle, espacios comunes, postes.
        - agua_cloacas: pérdidas, presión, olor, anegamientos, bombas cloacales.
        - espacios_verdes: pasto, poda, árboles caídos, riego, plagas.
        - obras: construcciones de vecinos, escombros, camiones, ruido de obra.
        - convivencia: ruidos molestos, mascotas sueltas, velocidad, estacionamiento.
        - administracion: expensas, documentación, y todo lo que no encaje arriba.

        Si dudás entre dos categorías, elegí administracion. Un reclamo mal derivado
        cuesta más que uno derivado a administración, que sabe redistribuirlo.

        # Emergencias

        Fuego, olor a gas, intrusión en curso, personas heridas o riesgo eléctrico no
        se triagean conversando. Cortá las preguntas, decile que llame ya mismo a la
        guardia al {$telefono}, y registrá el reclamo con urgencia "emergencia" en ese
        mismo turno, con lo que tengas.

        # Tono

        Cordial y breve, de vecino a vecino. Tuteo argentino. Nada de emojis salvo en
        el resumen de confirmación. Frases cortas.

        # Fuera de alcance

        No consultás expensas, saldos, morosidad ni turnos de canchas: eso está en el
        menú de la app y no tenés acceso. Si te lo piden, decilo en una línea y ofrecé
        tomar un reclamo si lo que hay es un problema.

        No prometas plazos de resolución ni digas quién va a ir. Solo confirmás que el
        reclamo quedó derivado.
        PROMPT;

        return [[
            'type' => 'text',
            'text' => $texto,
            // El prefijo (tools + system) se cachea: es idéntico en cada request
            // y en cada usuario. El mínimo cacheable en Opus 5 es 512 tokens.
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    private function tools(): array
    {
        return [[
            'name' => 'crear_reclamo',
            'description' => 'Registra el reclamo y lo deriva al área correspondiente. '
                . 'Usala únicamente después de que el vecino confirmó el resumen, '
                . 'salvo que sea una emergencia, donde la usás de inmediato.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'categoria' => [
                        'type' => 'string',
                        'enum' => [
                            'mantenimiento', 'seguridad', 'alumbrado', 'agua_cloacas',
                            'espacios_verdes', 'obras', 'convivencia', 'administracion',
                        ],
                        'description' => 'Área que debe resolver el reclamo.',
                    ],
                    'urgencia' => [
                        'type' => 'string',
                        'enum' => ['emergencia', 'alta', 'normal'],
                        'description' => 'emergencia: riesgo de vida o daño inmediato. '
                            . 'alta: afecta a varios vecinos o servicios esenciales. '
                            . 'normal: el resto.',
                    ],
                    'ubicacion_tipo' => [
                        'type' => 'string',
                        'enum' => ['lote', 'espacio_comun'],
                        'description' => 'Si el problema está dentro de un lote o en un espacio común.',
                    ],
                    'ubicacion_detalle' => [
                        'type' => 'string',
                        'description' => 'Número de lote, o referencia del espacio común '
                            . '(ej: "vereda esquina lote 47", "cancha de tenis 2").',
                    ],
                    'resumen' => [
                        'type' => 'string',
                        'description' => 'Una línea, máximo 80 caracteres. Es lo que ve el responsable en la notificación.',
                    ],
                    'descripcion' => [
                        'type' => 'string',
                        'description' => 'El detalle completo en tus palabras, incluyendo desde cuándo ocurre.',
                    ],
                ],
                'required' => [
                    'categoria', 'urgencia', 'ubicacion_tipo',
                    'ubicacion_detalle', 'resumen', 'descripcion',
                ],
                'additionalProperties' => false,
            ],
        ]];
    }

    /**
     * @return array{mensaje: string, reclamo?: Reclamo, error?: bool}
     */
    private function ejecutarTool(User $user, array $toolUse): array
    {
        if (($toolUse['name'] ?? null) !== 'crear_reclamo') {
            return ['mensaje' => 'Herramienta desconocida.', 'error' => true];
        }

        try {
            $reclamo = $this->crearReclamo($user, $toolUse['input'] ?? []);
        } catch (\Throwable $e) {
            Log::error('Error creando reclamo desde el chatbot', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return [
                'mensaje' => 'No se pudo registrar el reclamo por un error del sistema. '
                    . 'Pedile al vecino que lo intente de nuevo en unos minutos.',
                'error' => true,
            ];
        }

        return [
            'mensaje' => "Reclamo #{$reclamo->id} registrado y derivado a {$reclamo->derivado_a}. "
                . 'Confirmáselo al vecino con el número, en una línea.',
            'reclamo' => $reclamo,
        ];
    }

    /**
     * El user_id y el lote se inyectan acá desde la sesión autenticada: el modelo
     * nunca los pasa como parámetro. Mismo criterio que ya se aplica en las rutas
     * de FCM para evitar IDOR.
     */
    private function crearReclamo(User $user, array $input): Reclamo
    {
        $ruteo = ReclamoRuteo::paraCategoria($input['categoria']);
        $lotes = $this->lotesDe($user->email);

        $reclamo = Reclamo::create([
            'user_id'           => $user->id,
            'nlote'             => $lotes[0] ?? null,
            'categoria'         => $input['categoria'],
            'urgencia'          => $input['urgencia'],
            'ubicacion_tipo'    => $input['ubicacion_tipo'],
            'ubicacion_detalle' => mb_substr($input['ubicacion_detalle'], 0, 255),
            'resumen'           => mb_substr($input['resumen'], 0, 255),
            'descripcion'       => $input['descripcion'],
            'estado'            => 'nuevo',
            'derivado_a'        => $ruteo->nombre ?? 'Administración',
            'derivado_email'    => $ruteo->email ?? config('services.reclamos.email_fallback'),
        ]);

        $this->notificar($reclamo, $ruteo, $user);

        return $reclamo;
    }

    /**
     * El reclamo vive en la base; esto son punteros que apuntan ahí.
     * Ninguna falla de notificación puede tumbar la creación del reclamo.
     */
    private function notificar(Reclamo $reclamo, ?ReclamoRuteo $ruteo, User $vecino): void
    {
        $prefijo = $reclamo->esEmergencia() ? '🚨 EMERGENCIA' : '📋 Nuevo reclamo';
        $titulo  = "{$prefijo} #{$reclamo->id} — {$reclamo->derivado_a}";
        $cuerpo  = "{$reclamo->resumen} · {$reclamo->ubicacionLegible()}";

        // Push + campana in-app al responsable, si es un usuario del sistema.
        if ($ruteo && $ruteo->user_id) {
            try {
                $responsable = User::find($ruteo->user_id);

                if ($responsable) {
                    $tokens = $responsable->fcmTokens()->pluck('token')->toArray();

                    if (! empty($tokens)) {
                        FcmService::send($tokens, $titulo, $cuerpo);
                    }

                    UserNotification::create([
                        'user_id' => $responsable->id,
                        'title'   => $titulo,
                        'body'    => $cuerpo,
                        'is_read' => false,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Error notificando reclamo por push: ' . $e->getMessage());
            }
        }

        // Mail al área (o al fallback de administración).
        $destino = $reclamo->derivado_email ?: config('services.reclamos.email_fallback');

        if ($destino) {
            try {
                $nombreVecino = trim($vecino->nombre) !== '' ? $vecino->nombre : $vecino->email;

                $texto = "{$prefijo} #{$reclamo->id}\n\n"
                    . "Categoría: {$reclamo->derivado_a}\n"
                    . "Urgencia: {$reclamo->urgencia}\n"
                    . "Ubicación: {$reclamo->ubicacionLegible()}\n"
                    . "Reporta: {$nombreVecino}"
                    . ($reclamo->nlote ? " (lote {$reclamo->nlote})" : '') . "\n"
                    . "Contacto: {$vecino->email}\n\n"
                    . "Detalle:\n{$reclamo->descripcion}\n";

                Mail::raw($texto, function ($m) use ($destino, $titulo) {
                    $m->to($destino)->subject($titulo);
                });
            } catch (\Throwable $e) {
                Log::error('Error notificando reclamo por mail: ' . $e->getMessage());
            }
        }

        // Copia en la campana del vecino, para que le quede el número a mano.
        try {
            UserNotification::create([
                'user_id' => $vecino->id,
                'title'   => "Reclamo #{$reclamo->id} registrado",
                'body'    => "Derivado a {$reclamo->derivado_a}: {$reclamo->resumen}",
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error notificando al vecino: ' . $e->getMessage());
        }
    }

    /**
     * Los lotes no están en users: se derivan de gastoscomunes por email,
     * igual que en FacturaController::getLotesPorEmail.
     */
    private function lotesDe(string $email): array
    {
        try {
            return DB::table('gastoscomunes')
                ->where('email', $email)
                ->distinct()
                ->orderByRaw('CAST(nlote AS UNSIGNED) ASC')
                ->pluck('nlote')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('Error buscando lotes del vecino: ' . $e->getMessage());

            return [];
        }
    }
}
