<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EnvioMasivo;
use App\Models\GastosComunes;
use App\Services\EnviosMasivosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastosNotificacionesController extends Controller
{
    /**
     * Encola el aviso "Nuevos gastos comunes disponibles" para todos los de
     * gastoscomunes_notificaciones. No manda nada acá: lo hace el comando
     * `envios:procesar` de a tandas.
     *
     * Un aviso por período. Si ya se encoló el del período actual, responde 409
     * con su progreso, salvo que venga `forzar: true` (reenvío a propósito).
     *
     * El permiso lo da el middleware `admin` de la ruta, a partir del token.
     */
    public function notificar(Request $request, EnviosMasivosService $envios): JsonResponse
    {
        $periodo = GastosComunes::max('numero');
        $periodo = $periodo !== null ? (string) $periodo : null;
        $forzar  = $request->boolean('forzar');

        $emails = DB::table('gastoscomunes_notificaciones')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->distinct()
            ->pluck('email')
            ->all();

        if (empty($emails)) {
            return response()->json([
                'message' => 'No se encontraron emails para enviar.',
            ], 404);
        }

        [$envio, $creado] = $envios->encolar(
            EnvioMasivo::TIPO_GASTOS_COMUNES,
            $envios->claveGastos($periodo, $forzar),
            $emails,
            ['periodo' => $periodo, 'asunto' => 'Nuevos gastos comunes disponibles'],
            optional($request->user())->id
        );

        if (! $creado) {
            return response()->json([
                'message' => "El aviso del período {$periodo} ya fue enviado o se está enviando.",
                'envio'   => $envio->resumen(),
            ], 409);
        }

        return response()->json([
            'message' => 'Aviso encolado. Se envía en los próximos minutos.',
            'envio'   => $envio->resumen(),
        ], 202);
    }
}
