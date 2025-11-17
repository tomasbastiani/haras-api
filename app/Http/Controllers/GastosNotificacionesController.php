<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User; // lo dejamos por si después lo volvemos a usar
use App\Notifications\GastosComunesDisponiblesNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class GastosNotificacionesController extends Controller
{
    public function notificar(Request $request): JsonResponse
    {
        // 1) Check admin simple
        $isAdmin = filter_var($request->input('is_admin'), FILTER_VALIDATE_BOOLEAN);

        if (! $isAdmin) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 403);
        }

        // 2) Periodo opcional
        $periodo = $request->input('periodo');

        // 3) Lista hardcodeada de correos de prueba
        $testEmails = [
            'tomas.bastiani@hotmail.com',
            'Leobastiani@outlook.com',
        ];
        $nombre = $notifiable->nombre ?? 'vecino';

        Log::info('Enviando emails de prueba (Gastos Comunes)', [
            'periodo' => $periodo,
            'emails'  => $testEmails,
            'total'   => count($testEmails),
        ]);

        // 4) Enviar la notificación SOLO a estos dos emails
        Notification::route('mail', $testEmails)
            ->notify(new GastosComunesDisponiblesNotification($periodo));

        return response()->json([
            'message' => 'Correos de prueba enviados correctamente.',
        ]);
    }
}
