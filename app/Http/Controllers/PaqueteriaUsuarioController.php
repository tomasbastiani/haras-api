<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Alta y baja de las cuentas dedicadas de paquetería (portería).
 *
 * Existe porque hasta ahora la única forma de habilitar a alguien en la oficina
 * era un INSERT a mano en `paqueteria_operarios`. Todo lo de acá va detrás del
 * middleware `admin`.
 */
class PaqueteriaUsuarioController extends Controller
{
    /** Cuentas de portería activas. */
    public function index()
    {
        $usuarios = User::where('paqueteria', 1)
            ->orderBy('nombre')
            ->orderBy('email')
            ->get(['id', 'nombre', 'email', 'admin', 'paqueteria', 'created_at']);

        return response()->json($usuarios);
    }

    /**
     * Busca cuentas existentes para convertirlas en cuenta de portería.
     *
     * Sirve para el caso del empleado que ya tenía usuario en la app. Devuelve
     * pocos resultados a propósito: es un buscador, no un listado de la base.
     */
    public function buscar(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:150']);

        $q = $request->q;

        $usuarios = User::where(function ($sub) use ($q) {
                $sub->where('email', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            })
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'nombre', 'email', 'admin', 'paqueteria']);

        return response()->json($usuarios);
    }

    /**
     * Crea una cuenta de portería desde cero.
     *
     * Es el camino habitual: el personal de portería no tiene lote, así que no
     * existe como usuario (los usuarios se crean a partir de gastoscomunes).
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'   => 'required|string|max:150',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'email.unique'      => 'Ya existe un usuario con ese email. Buscalo y habilitalo en vez de crearlo.',
            'password.regex'    => 'La contraseña debe tener al menos una mayúscula y un número.',
        ]);

        $user = new User();
        $user->nombre     = $datos['nombre'];
        $user->email      = $datos['email'];
        $user->password   = Hash::make($datos['password']);
        $user->admin      = 0;
        $user->paqueteria = true;
        $user->save();

        return response()->json([
            'message' => 'Cuenta de paquetería creada.',
            'usuario' => $user->only(['id', 'nombre', 'email', 'admin', 'paqueteria']),
        ], 201);
    }

    /**
     * Habilita o deshabilita una cuenta existente como cuenta de portería.
     *
     * No se permite sobre un admin: la cuenta de paquetería tiene el menú
     * recortado a la oficina, así que marcar a un admin lo dejaría sin acceso
     * al panel —incluido este—, y se necesitaría entrar a la base para revertirlo.
     */
    public function update(Request $request, $id)
    {
        $datos = $request->validate(['paqueteria' => 'required|boolean']);

        $user = User::findOrFail($id);

        if ($datos['paqueteria'] && (int) $user->admin === 1) {
            return response()->json([
                'message' => 'No se puede convertir a un administrador en cuenta de paquetería: perdería el acceso al panel. Los admins ya pueden operar la oficina.',
            ], 422);
        }

        $user->paqueteria = $datos['paqueteria'];
        $user->save();

        return response()->json([
            'message' => $datos['paqueteria']
                ? 'La cuenta ahora tiene acceso a la oficina de paquetería.'
                : 'Se le quitó el acceso a la oficina de paquetería.',
            'usuario' => $user->only(['id', 'nombre', 'email', 'admin', 'paqueteria']),
        ]);
    }

    /**
     * Resetea la contraseña de una cuenta de portería.
     *
     * Estas cuentas son compartidas por el personal de turno y no tienen un
     * email real detrás al que mandarle el link de recupero, así que el reset
     * lo hace administración a mano.
     */
    public function resetPassword(Request $request, $id)
    {
        $datos = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'password.regex' => 'La contraseña debe tener al menos una mayúscula y un número.',
        ]);

        $user = User::findOrFail($id);

        if (! $user->esPaqueteria()) {
            return response()->json([
                'message' => 'Esta cuenta no es una cuenta de paquetería.',
            ], 422);
        }

        $user->password = Hash::make($datos['password']);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
