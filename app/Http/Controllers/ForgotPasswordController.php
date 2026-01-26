<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * POST /password/forgot
     * Recibe email, genera token y envía link
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $genericMessage = 'Si el correo existe, vas a recibir un email con instrucciones para recuperar tu contraseña.';

        /** @var User|null $user */
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // No revelamos si existe o no
            return response()->json([
                'message' => $genericMessage,
            ]);
        }

        // Generar token
        $token = Str::random(64);

        // Guardar en tabla password_resets (creada por vos)
        DB::table('password_resets')->insert([
            'email'      => $user->email,
            'token'      => $token,
            'created_at' => Carbon::now(),
        ]);

        // Armar link al frontend: /reset-password?token=...&email=...
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL'));
        $resetLink   = $frontendUrl
            . '/reset-password?token=' . urlencode($token)
            . '&email=' . urlencode($user->email);

        // Enviar mail
        Mail::to($user->email)->send(new ResetPasswordMail($resetLink));

        return response()->json([
            'message' => $genericMessage,
        ]);
    }

    /**
     * POST /password/reset
     * Recibe token + email + nueva pass y actualiza
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:6|confirmed',
            // password_confirmation viene automático por "confirmed"
        ]);

        $email = $request->email;
        $token = $request->token;

        // Buscar registro en password_resets
        $reset = DB::table('password_resets')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (!$reset) {
            return response()->json([
                'message' => 'Token inválido o ya utilizado.',
            ], 400);
        }

        // Verificar expiración (ej: 60 minutos)
        $createdAt = Carbon::parse($reset->created_at);
        if ($createdAt->diffInMinutes(Carbon::now()) > 60) {
            // Borrar token vencido
            DB::table('password_resets')
                ->where('email', $email)
                ->where('token', $token)
                ->delete();

            return response()->json([
                'message' => 'El enlace de recuperación ha expirado.',
            ], 400);
        }

        // Actualizar password del usuario
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Por si entre medio borraron el user
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $user->password = $request->password;
        $user->save();

        // Borrar todos los tokens de ese email (para que no se puedan reutilizar)
        DB::table('password_resets')
            ->where('email', $email)
            ->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }
}
