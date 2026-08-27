<?php

namespace App\Http\Controllers;

use App\Models\Reclamo;
use App\Models\ReclamoRuteo;
use App\Models\UserNotification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReclamoController extends Controller
{
    /**
     * Bandeja de reclamos. Un admin ve todo; un responsable de área ve
     * únicamente las categorías que tiene asignadas.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $categorias = $this->categoriasDe($user->id);

        if (! $user->admin && empty($categorias)) {
            return response()->json(['message' => 'No tenés reclamos asignados.'], 403);
        }

        $query = Reclamo::with('user:id,nombre,email')->latest();

        if (! $user->admin) {
            $query->whereIn('categoria', $categorias);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->limit(200)->get());
    }

    /**
     * Reclamos del propio vecino, para que pueda seguirlos desde la app.
     */
    public function mios(Request $request)
    {
        return response()->json(
            Reclamo::where('user_id', $request->user()->id)->latest()->limit(50)->get()
        );
    }

    public function tomar(Request $request, $id)
    {
        $reclamo = Reclamo::findOrFail($id);

        if (! $this->puedeGestionar($request->user(), $reclamo)) {
            return response()->json(['message' => 'No tenés permiso sobre este reclamo.'], 403);
        }

        if ($reclamo->estado !== 'nuevo') {
            return response()->json(['message' => 'El reclamo ya fue tomado.'], 409);
        }

        $reclamo->update([
            'estado'     => 'tomado',
            'tomado_por' => $request->user()->id,
            'tomado_at'  => now(),
        ]);

        $this->avisarAlVecino(
            $reclamo,
            "Reclamo #{$reclamo->id} en curso",
            "{$reclamo->derivado_a} tomó tu reclamo: {$reclamo->resumen}"
        );

        return response()->json(['message' => 'Reclamo tomado.', 'reclamo' => $reclamo]);
    }

    public function resolver(Request $request, $id)
    {
        $request->validate([
            'nota_cierre' => 'nullable|string|max:1000',
        ]);

        $reclamo = Reclamo::findOrFail($id);

        if (! $this->puedeGestionar($request->user(), $reclamo)) {
            return response()->json(['message' => 'No tenés permiso sobre este reclamo.'], 403);
        }

        if ($reclamo->estado === 'resuelto') {
            return response()->json(['message' => 'El reclamo ya estaba resuelto.'], 409);
        }

        $reclamo->update([
            'estado'      => 'resuelto',
            'resuelto_at' => now(),
            'nota_cierre' => $request->nota_cierre,
        ]);

        $this->avisarAlVecino(
            $reclamo,
            "Reclamo #{$reclamo->id} resuelto ✅",
            $request->nota_cierre ?: $reclamo->resumen
        );

        return response()->json(['message' => 'Reclamo resuelto.', 'reclamo' => $reclamo]);
    }

    private function categoriasDe(int $userId): array
    {
        return ReclamoRuteo::where('user_id', $userId)
            ->where('activo', true)
            ->pluck('categoria')
            ->all();
    }

    private function puedeGestionar($user, Reclamo $reclamo): bool
    {
        return $user->admin || in_array($reclamo->categoria, $this->categoriasDe($user->id), true);
    }

    private function avisarAlVecino(Reclamo $reclamo, string $titulo, string $cuerpo): void
    {
        try {
            $vecino = $reclamo->user;

            if (! $vecino) {
                return;
            }

            $tokens = $vecino->fcmTokens()->pluck('token')->toArray();

            if (! empty($tokens)) {
                FcmService::send($tokens, $titulo, $cuerpo);
            }

            UserNotification::create([
                'user_id' => $vecino->id,
                'title'   => $titulo,
                'body'    => $cuerpo,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error avisando cambio de estado de reclamo: ' . $e->getMessage());
        }
    }
}
