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
     * clic, reintento tras error de red): responde 409, salvo `forzar: true`.
     */
    public function sendCustomMail(SendCustomMailRequest $request, EnviosMasivosService $envios): JsonResponse
    {
        $emails  = $request->input('emails', []);
        $subject = $request->input('subject');
        $bodyTpl = $request->input('body');
        $forzar  = $request->boolean('forzar');

        [$envio, $creado] = $envios->encolar(
            EnvioMasivo::TIPO_PERSONALIZADO,
            $envios->clavePersonalizado($subject, $bodyTpl, $emails, $forzar),
            $emails,
            ['asunto' => $subject, 'cuerpo' => $bodyTpl],
            optional($request->user())->id
        );

        if (! $creado) {
            return response()->json([
                'status'  => 'duplicado',
                'message' => 'Este mismo mail a estos mismos destinatarios ya fue enviado o se está enviando.',
                'envio'   => $envio->resumen(),
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
}
