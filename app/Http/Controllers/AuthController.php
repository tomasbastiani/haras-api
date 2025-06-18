<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        \Log::info('Login request', $request->all());

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            \Log::warning('Usuario no encontrado: ' . $credentials['email']);
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if ($user->password !== $credentials['password']) {
            \Log::warning('Password incorrecto para: ' . $credentials['email']);
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // Login exitoso - generar token o sesión
        \Log::info('Login correcto para: ' . $credentials['email']);
        // Aquí podés generar un token si lo necesitás, o simplemente responder
        return response()->json(['message' => 'Login correcto', 'user' => $user]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // Comparación directa si las contraseñas están en texto plano (NO recomendado)
        if ($request->current_password !== $user->password) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.'
            ], 400);
        }

        if ($request->current_password === $request->new_password) {
            return response()->json([
                'message' => 'La nueva contraseña no puede ser igual a la actual.'
            ], 400);
        }

        // Guardar nueva contraseña como texto plano (NO recomendado)
        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.'
        ]);
    }

}
