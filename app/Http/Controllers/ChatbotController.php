<?php

namespace App\Http\Controllers;

use App\Models\ChatConversacion;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /** Minutos de inactividad tras los cuales la conversación se da por terminada. */
    private const TIMEOUT_MINUTOS = 60;

    public function __construct(private ChatbotService $chatbot)
    {
    }

    /**
     * Envía un mensaje del vecino y devuelve la respuesta del bot.
     *
     * El usuario sale SIEMPRE del token de Sanctum, nunca del body.
     */
    public function mensaje(Request $request)
    {
        $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        $user = $request->user();
        $conversacion = $this->conversacionActiva($user->id);

        try {
            $resultado = $this->chatbot->responder($user, $conversacion, $request->mensaje);
        } catch (\Throwable $e) {
            Log::error('Error en el chatbot', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'respuesta' => 'Se cayó la conexión con el asistente. Probá de nuevo en un momento.',
                'error'     => true,
            ], 503);
        }

        return response()->json([
            'respuesta' => $resultado['respuesta'],
            'reclamo'   => $resultado['reclamo']
                ? [
                    'id'         => $resultado['reclamo']->id,
                    'derivado_a' => $resultado['reclamo']->derivado_a,
                    'urgencia'   => $resultado['reclamo']->urgencia,
                ]
                : null,
        ]);
    }

    /**
     * Historial de la conversación abierta, para reconstruir el chat al reabrir
     * el widget o recargar la página.
     */
    public function historial(Request $request)
    {
        $conversacion = ChatConversacion::where('user_id', $request->user()->id)
            ->where('estado', 'activa')
            ->where('updated_at', '>=', now()->subMinutes(self::TIMEOUT_MINUTOS))
            ->latest()
            ->first();

        if (! $conversacion) {
            return response()->json(['mensajes' => []]);
        }

        return response()->json(['mensajes' => $this->transcripcion($conversacion)]);
    }

    /**
     * Cierra la conversación actual. El próximo mensaje arranca una nueva.
     */
    public function reiniciar(Request $request)
    {
        ChatConversacion::where('user_id', $request->user()->id)
            ->where('estado', 'activa')
            ->update(['estado' => 'cerrada']);

        return response()->json(['message' => 'Conversación reiniciada']);
    }

    private function conversacionActiva(int $userId): ChatConversacion
    {
        $conversacion = ChatConversacion::where('user_id', $userId)
            ->where('estado', 'activa')
            ->where('updated_at', '>=', now()->subMinutes(self::TIMEOUT_MINUTOS))
            ->latest()
            ->first();

        if ($conversacion) {
            return $conversacion;
        }

        // Cerramos cualquier conversación vencida antes de abrir una nueva.
        ChatConversacion::where('user_id', $userId)
            ->where('estado', 'activa')
            ->update(['estado' => 'cerrada']);

        return ChatConversacion::create([
            'user_id'  => $userId,
            'estado'   => 'activa',
            'mensajes' => [],
        ]);
    }

    /**
     * Convierte el hilo crudo de la API en algo mostrable: descarta thinking
     * blocks, tool_use y tool_result, y le saca al primer mensaje el bloque de
     * contexto que se le inyecta al modelo.
     */
    private function transcripcion(ChatConversacion $conversacion): array
    {
        $salida = [];

        foreach ($conversacion->hilo() as $mensaje) {
            $rol = $mensaje['role'] ?? null;
            $contenido = $mensaje['content'] ?? null;

            if ($rol === 'user') {
                if (! is_string($contenido)) {
                    continue; // tool_result: no se muestra
                }

                $texto = $contenido;

                if (($pos = strpos($texto, "[Mensaje]\n")) !== false) {
                    $texto = substr($texto, $pos + strlen("[Mensaje]\n"));
                }

                $salida[] = ['rol' => 'user', 'texto' => trim($texto)];
                continue;
            }

            if ($rol === 'assistant' && is_array($contenido)) {
                $partes = [];

                foreach ($contenido as $bloque) {
                    if (($bloque['type'] ?? null) === 'text' && ! empty($bloque['text'])) {
                        $partes[] = $bloque['text'];
                    }
                }

                if ($partes) {
                    $salida[] = ['rol' => 'bot', 'texto' => trim(implode("\n\n", $partes))];
                }
            }
        }

        return $salida;
    }
}
