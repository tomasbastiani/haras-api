<?php

namespace App\Http\Controllers;

use App\Mail\TurnoCanceladoMail;
use App\Mail\TurnoConfirmadoMail;
use App\Models\Cancha;
use App\Models\Turno;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TurneroController extends Controller
{
    private const HORA_INICIO = 8;
    private const HORA_FIN = 22;
    private const DIAS_ADELANTE = 10;
    private const MAX_TURNOS_ACTIVOS_POR_DEPORTE = 2;
    private const HORAS_MIN_CANCELACION = 24;

    public function canchas()
    {
        return response()->json(
            Cancha::where('activa', true)->orderBy('tipo')->orderBy('orden')->get()
        );
    }

    public function disponibilidad(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:futbol,tenis',
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        $canchas = Cancha::where('tipo', $request->tipo)
            ->where('activa', true)
            ->get();

        $capacidad = $canchas->count();

        $reservadosPorHora = Turno::activos()
            ->whereIn('cancha_id', $canchas->pluck('id'))
            ->where('fecha', $request->fecha)
            ->get()
            ->groupBy(fn ($t) => substr($t->hora_inicio, 0, 5))
            ->map->count();

        $esHoy = $request->fecha === now()->toDateString();
        $horaActual = now()->format('H:i');

        $horarios = [];
        for ($h = self::HORA_INICIO; $h < self::HORA_FIN; $h++) {
            $hora = sprintf('%02d:00', $h);
            $reservados = $reservadosPorHora->get($hora, 0);
            $disponibles = $capacidad - $reservados;

            if ($esHoy && $hora <= $horaActual) {
                $estado = 'pasado';
            } elseif ($disponibles <= 0) {
                $estado = 'ocupado';
            } else {
                $estado = 'disponible';
            }

            $horarios[] = [
                'hora'        => $hora,
                'estado'      => $estado,
                'disponibles' => max(0, $disponibles),
                'reservados'  => $reservados,
            ];
        }

        return response()->json([
            'capacidad' => $capacidad,
            'horarios'  => $horarios,
        ]);
    }

    public function reservar(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'tipo'  => 'required|in:futbol,tenis',
            'nlote' => 'nullable|string',
            'fecha' => 'required|date_format:Y-m-d',
            'hora'  => 'required|date_format:H:i',
        ]);

        $user = User::where('email', $request->email)->first();

        $fechaHoraInicio = Carbon::parse("{$request->fecha} {$request->hora}");

        if ($fechaHoraInicio->lt(now())) {
            return response()->json(['message' => 'No se puede reservar un turno en el pasado.'], 422);
        }

        if ($fechaHoraInicio->gt(now()->addDays(self::DIAS_ADELANTE))) {
            return response()->json(['message' => 'Esa fecha todavía no está habilitada para reservar.'], 422);
        }

        $resultado = DB::transaction(function () use ($request, $user, $fechaHoraInicio) {
            $canchas = Cancha::where('tipo', $request->tipo)
                ->where('activa', true)
                ->orderBy('orden')
                ->get();

            // Bloqueamos todas las filas de ese tipo/fecha/hora para evitar doble reserva por carrera de clics
            $ocupadas = Turno::whereIn('cancha_id', $canchas->pluck('id'))
                ->where('fecha', $request->fecha)
                ->where('hora_inicio', $request->hora)
                ->where('estado', 'reservado')
                ->lockForUpdate()
                ->pluck('cancha_id');

            if ($ocupadas->count() >= $canchas->count()) {
                return ['error' => 'ocupado'];
            }

            $activos = Turno::where('user_id', $user->id)
                ->where('estado', 'reservado')
                ->where('fecha', '>=', now()->toDateString())
                ->whereHas('cancha', fn ($q) => $q->where('tipo', $request->tipo))
                ->count();

            if ($activos >= self::MAX_TURNOS_ACTIVOS_POR_DEPORTE) {
                return ['error' => 'limite'];
            }

            $cancha = $canchas->whereNotIn('id', $ocupadas->toArray())->first();

            $turno = Turno::create([
                'cancha_id'   => $cancha->id,
                'user_id'     => $user->id,
                'nlote'       => $request->nlote,
                'fecha'       => $request->fecha,
                'hora_inicio' => $request->hora,
                'hora_fin'    => $fechaHoraInicio->copy()->addHour()->format('H:i'),
                'estado'      => 'reservado',
            ]);

            return ['turno' => $turno];
        });

        if (isset($resultado['error']) && $resultado['error'] === 'ocupado') {
            return response()->json(['message' => 'No quedan canchas disponibles para ese horario.'], 409);
        }

        if (isset($resultado['error']) && $resultado['error'] === 'limite') {
            return response()->json([
                'message' => 'Alcanzaste el máximo de turnos activos para este deporte (' . self::MAX_TURNOS_ACTIVOS_POR_DEPORTE . ').',
            ], 422);
        }

        $turno = $resultado['turno']->load('cancha');
        $this->notificarTurno($user, $turno, 'confirmado');

        return response()->json(['message' => 'Turno reservado con éxito.', 'turno' => $turno]);
    }

    public function cancelar(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'is_admin' => 'nullable|boolean',
        ]);

        $turno = Turno::with(['cancha', 'user'])->findOrFail($id);
        $user = User::where('email', $request->email)->first();
        $esAdmin = $request->boolean('is_admin');

        if (!$esAdmin && $turno->user_id !== $user->id) {
            return response()->json(['message' => 'No podés cancelar un turno que no es tuyo.'], 403);
        }

        if ($turno->estado === 'cancelado') {
            return response()->json(['message' => 'Ese turno ya estaba cancelado.'], 422);
        }

        $fechaHoraInicio = Carbon::parse($turno->fecha->format('Y-m-d') . ' ' . $turno->hora_inicio);

        if (!$esAdmin && now()->diffInHours($fechaHoraInicio, false) < self::HORAS_MIN_CANCELACION) {
            return response()->json([
                'message' => 'Solo se puede cancelar hasta 24 horas antes del turno.',
            ], 422);
        }

        $titular = $turno->user;

        $turno->update([
            'estado' => 'cancelado',
            'cancelado_at' => now(),
            'cancelado_por' => $user->id,
        ]);

        $this->notificarTurno($titular, $turno, 'cancelado');

        return response()->json(['message' => 'Turno cancelado correctamente.']);
    }

    public function misTurnos(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        $turnos = Turno::with('cancha')
            ->where('user_id', $user->id)
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        return response()->json($turnos);
    }

    public function adminTurnos(Request $request)
    {
        $request->validate([
            'fecha' => 'nullable|date_format:Y-m-d',
            'tipo' => 'nullable|in:futbol,tenis',
        ]);

        $query = Turno::with(['cancha', 'user'])
            ->where('estado', 'reservado')
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($request->fecha) {
            $query->where('fecha', $request->fecha);
        }

        if ($request->tipo) {
            $query->whereHas('cancha', fn ($q) => $q->where('tipo', $request->tipo));
        }

        $turnos = $query->get()->map(fn ($turno) => [
            'id' => $turno->id,
            'cancha' => $turno->cancha->nombre,
            'tipo' => $turno->cancha->tipo,
            'fecha' => $turno->fecha->format('Y-m-d'),
            'hora' => substr($turno->hora_inicio, 0, 5),
            'nlote' => $turno->nlote,
            'propietario' => [
                'nombre' => $turno->user->nombre,
                'email' => $turno->user->email,
            ],
        ]);

        return response()->json($turnos->values());
    }

    private function notificarTurno(User $user, Turno $turno, string $tipo)
    {
        $horaFmt = substr($turno->hora_inicio, 0, 5);
        $fechaFmt = Carbon::parse($turno->fecha)->format('d/m');
        $titulo = $tipo === 'confirmado' ? '✅ Turno confirmado' : '❌ Turno cancelado';
        $cuerpo = "{$turno->cancha->nombre} - {$fechaFmt} {$horaFmt}hs";

        try {
            $mailable = $tipo === 'confirmado'
                ? new TurnoConfirmadoMail($turno)
                : new TurnoCanceladoMail($turno);

            Mail::to($user->email)->send($mailable);
        } catch (\Exception $e) {
            Log::error('Error enviando mail de turno: ' . $e->getMessage());
        }

        try {
            $tokens = $user->fcmTokens()->pluck('token')->toArray();

            if (!empty($tokens)) {
                FcmService::send($tokens, $titulo, $cuerpo);
            }

            UserNotification::create([
                'user_id' => $user->id,
                'title' => $titulo,
                'body' => $cuerpo,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando push de turno: ' . $e->getMessage());
        }
    }
}
