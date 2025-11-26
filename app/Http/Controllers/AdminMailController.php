<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCustomMailRequest;
use App\Mail\CustomAdminMail;
use App\Models\GastosComunes;
use App\Models\User;
use App\Models\Moroso;
use App\Models\InfoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminMailController extends Controller
{
    public function sendCustomMail(SendCustomMailRequest $request): JsonResponse
    {
        $emails   = $request->input('emails', []);
        $subject  = $request->input('subject');
        $bodyTpl  = $request->input('body');

        $sent         = 0;
        $failures     = [];

        foreach ($emails as $email) {
            try {
                // ===== USERS =====
                $user = User::where('email', $email)->first();
                $nombre = $user?->name
                    ?? $user?->nombre
                    ?? '';

                // ===== 1) GASTOS COMUNES (NLotes únicos) =====
                $gastos = GastosComunes::where('email', $email)
                    ->distinct()
                    ->pluck('nlote')
                    ->toArray();

                $lotesNormales = $gastos; // para {lote}

                // ===== 2) MOROSOS =====
                $morosos = Moroso::where('email', $email)->get();

                $lotesMorosos = $morosos->pluck('nlote')->unique()->toArray();

                // Creamos un MAPA lote => monto
                $montosPorLote = [];
                foreach ($morosos as $m) {
                    $montosPorLote[$m->nlote] = $m->monto;
                }

                // ===== 3) INFO PAGOS: CVU y ALIAS por lote =====
                $nlotesParaPago = array_unique(array_merge($lotesNormales, $lotesMorosos));

                $infoPagos = InfoPago::whereIn('nlote', $nlotesParaPago)->get();

                // Creamos un MAPA lote => [cvu, alias]
                $pagosPorLote = [];
                foreach ($infoPagos as $ip) {
                    $pagosPorLote[$ip->nlote] = [
                        'cvu'   => $ip->cvu ?? '',
                        'alias' => $ip->alias ?? '',
                    ];
                }

                // ===== 4) Construcción del TEXTO unificado =====
                $detallePorLote = [];

                foreach ($nlotesParaPago as $nl) {
                    $monto = $montosPorLote[$nl] ?? '';
                    $cvu   = $pagosPorLote[$nl]['cvu']   ?? '';
                    $alias = $pagosPorLote[$nl]['alias'] ?? '';

                    $detallePorLote[] =
                        "Lote: $nl\n" .
                        "- Monto: $$monto\n" .
                        "- CVU: $cvu\n" .
                        "- Alias: $alias\n";
                }

                $detalleFinal = implode("\n\n", $detallePorLote);

                // ===== 5) PLACEHOLDERS =====
                $bodyFinal = $this->replacePlaceholders($bodyTpl, [
                    '{nombre}'      => $nombre,
                    '{lote}'        => implode(', ', $lotesNormales),
                    '{lotemoroso}'  => implode(', ', $lotesMorosos),
                    '{detalleDeudaxLote}'     => $detalleFinal,
                ]);


                Mail::to($email)->send(new CustomAdminMail($subject, $bodyFinal));
                $sent++;

            } catch (\Throwable $e) {
                Log::error('Error enviando mail personalizado', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                $failures[] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Proceso de envío finalizado.',
            'data'    => [
                'total'     => count($emails),
                'sent'      => $sent,
                'failures'  => $failures,
            ],
        ]);
    }

    protected function replacePlaceholders(string $template, array $vars): string
    {
        return strtr($template, $vars);
    }
}
