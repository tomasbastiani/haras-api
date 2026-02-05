<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GastosComunes;
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

        // 2) Periodo opcional (se lo pasamos a la notificación para que lo use en el mail)
        $periodo = GastosComunes::max('numero');

        /**
         * ==========================
         *  MODO PRUEBA (COMENTADO)
         * ==========================
         */




        // $testEmails = [
        //     'tomas.bastiani@hotmail.com',
        //     'totoprofly89@gmail.com',
        // ];

        // Log::info('Enviando emails de PRUEBA (Gastos Comunes)', [
        //     'periodo' => $periodo,
        //     'emails'  => $testEmails,
        //     'total'   => count($testEmails),
        // ]);

        // // Notification::route('mail', $testEmails)
        // //     ->notify(new GastosComunesDisponiblesNotification($periodo));

        // foreach ($testEmails as $email) {
        //     Notification::route('mail', $email)
        //         ->notify(new GastosComunesDisponiblesNotification($periodo));
        // }

        // return response()->json([
        //     'message' => 'Correos de prueba enviados correctamente.',
        // ]);






        /**
         * ==========================
         *  MODO REAL: Mails desde BD
         * ==========================
         */


        // Traer emails desde la tabla gastoscomunes, SIN duplicados y sin nulos/vacíos
        $query = GastosComunes::query()
            ->whereNotNull('email')
            ->where('email', '<>', '');

        $emails = $query
            ->distinct('email')      // evitar duplicados a nivel query
            ->pluck('email')         // devolver solo la columna email
            ->toArray();             // convertir a array para Notification::route

        if (empty($emails)) {
            Log::warning('No se encontraron emails en gastoscomunes para enviar notificación de Gastos Comunes.', [
                'periodo' => $periodo,
            ]);

            return response()->json([
                'message' => 'No se encontraron emails para enviar.',
            ], 404);
        }

        Log::info('Enviando emails REALES (Gastos Comunes)', [
            'periodo' => $periodo,
            'total'   => count($emails),
            // 'emails'  => $emails
        ]);

        // Enviar notificación a todos los emails reales
        // Notification::route('mail', $emails)
        //     ->notify(new GastosComunesDisponiblesNotification($periodo));

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new GastosComunesDisponiblesNotification($periodo));
        }


        return response()->json([
            'message' => 'Correos enviados correctamente.',
            'total'   => count($emails),
        ]);
    }
}
