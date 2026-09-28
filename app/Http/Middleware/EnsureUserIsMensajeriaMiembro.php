<?php

namespace App\Http\Middleware;

use App\Models\MensajeriaMiembro;
use Closure;
use Illuminate\Http\Request;

/**
 * Puerta de entrada a la mensajería interna.
 *
 * Corre en CADA endpoint del módulo, no sólo en el login. Es la diferencia
 * importante: las sesiones de esta app son eternas por diseño (useAuth.js
 * mantiene el token hasta que el usuario cierra sesión o el backend tira 401),
 * así que validar el acceso una sola vez al iniciar sesión le dejaría el chat
 * abierto a un empleado dado de baja hasta que él decidiera desloguearse.
 *
 * El flag que viaja en la respuesta del login y vive en localStorage es sólo
 * para pintar el menú y el guard del router: eso es UX, esto es el permiso.
 *
 * Admin de la app: pasa aunque no esté en el directorio, porque puede supervisar
 * los grupos. Lo que puede leer de cada canal lo decide Canal::puedeLeer(), que
 * le niega los mensajes directos incluso siendo admin.
 */
class EnsureUserIsMensajeriaMiembro
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if (MensajeriaMiembro::esMiembro((int) $user->id) || (int) $user->admin === 1) {
            return $next($request);
        }

        return response()->json(['message' => 'No tenés acceso a la mensajería interna'], 403);
    }
}
