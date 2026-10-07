<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCustomMailRequest;
use App\Models\EnvioMasivo;
use App\Services\EnviosMasivosService;
use Illuminate\Http\JsonResponse;

class AdminMailController extends Controller
{
    /**
     * Encola el mail personalizado. Los placeholders ({nombre}, {lote},
     * {detalledeudaxlote}, ...) se reemplazan por destinatario al momento de
     * mandar: ver EnviosMasivosService::cuerpoPersonalizado().
     *
     * El mismo asunto + cuerpo + destinatarios no se encola dos veces (doble
     * clic, reintento tras error de red): responde 409. Con `forzar: true` se
     * reenvía sólo a quienes todavía no lo recibieron.
     */
    public function sendCustomMail(SendCustomMailRequest $request, EnviosMasivosService $envios): JsonResponse
    {
        $emails  = $request->input('emails', []);
        $subject = $request->input('subject');
        $bodyTpl = $request->input('body');
        $forzar  = $request->boolean('forzar');

        [$envio, $resultado] = $envios->encolar(
            EnvioMasivo::TIPO_PERSONALIZADO,
            $envios->clavePersonalizado($subject, $bodyTpl, $emails),
            $forzar,
            $emails,
            ['asunto' => $subject, 'cuerpo' => $bodyTpl],
            optional($request->user())->id
        );

        if ($resultado === EnviosMasivosService::EN_CURSO) {
            return response()->json([
                'status'    => 'en_curso',
                'resultado' => $resultado,
                'message'   => 'Este mismo mail se está enviando o está pausado. Reanudalo o cancelalo antes de volver a enviar.',
                'envio'     => $envio->resumen(),
            ], 409);
        }

        if ($resultado === EnviosMasivosService::DUPLICADO) {
            return response()->json([
                'status'    => 'duplicado',
                'resultado' => $resultado,
                'message'   => 'Este mismo mail a estos mismos destinatarios ya fue enviado.',
                'envio'     => $envio->resumen(),
            ], 409);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Mail encolado. Se envía en los próximos minutos.',
            'envio'   => $envio->resumen(),
        ], 202);
    }

    /**
     * Progreso de un envío masivo (lo consulta el front mientras se manda).
     */
    public function estadoEnvio(int $id): JsonResponse
    {
        $envio = EnvioMasivo::findOrFail($id);

        return response()->json(['envio' => $envio->resumen()]);
    }

    /**
     * Pausar / reanudar / cancelar un envío masivo.
     */
    public function accionEnvio(int $id, string $accion, EnviosMasivosService $envios): JsonResponse
    {
        $envio = EnvioMasivo::findOrFail($id);

        if ($envio->finalizado_at !== null && $accion !== 'reanudar') {
            return response()->json(['message' => 'El envío ya terminó.', 'envio' => $envio->resumen()], 409);
        }

        $envios->cambiarEstado($envio, $accion);

        return response()->json(['envio' => $envio->fresh()->resumen()]);
    }
}
