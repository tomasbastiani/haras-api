<?php

namespace App\Http\Controllers;

use App\Models\MensajeriaMiembro;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Alta y baja de los empleados habilitados en la mensajería interna.
 *
 * Mismo esquema que PaqueteriaUsuarioController, y por el mismo motivo: sin esto
 * la única forma de habilitar a alguien sería un INSERT a mano. Todo detrás del
 * middleware `admin`.
 *
 * Los dos casos reales que resuelve:
 *
 *   1. El empleado que YA tiene cuenta porque además es propietario con lote:
 *      se lo busca y se lo habilita. Conserva intacto el resto de la app.
 *   2. El empleado sin lote, que no existe en `users`: se le crea la cuenta acá.
 *
 * Importante: las cuentas de la mensajería tienen que ser PERSONALES. La cuenta
 * de portería (users.paqueteria) es compartida por el personal de turno, así que
 * habilitarla dejaría mensajes sin autor identificable —justo lo que un chat de
 * trabajo necesita tener. Por eso store() la rechaza.
 */
class MensajeriaMiembroController extends Controller
{
    /**
     * Todos los habilitados, activos y dados de baja.
     *
     * Cada fila informa si apunta a la cuenta que realmente autentica
     * (`es_cuenta_de_login`). Las que no, son permisos inertes: están guardados
     * pero la persona no ve el módulo. El front las marca y ofrece corregirlas,
     * porque desde la base es invisible —la fila se ve perfectamente normal—.
     */
    public function index()
    {
        $miembros = MensajeriaMiembro::orderByDesc('activo')->get();

        $usuarios = User::whereIn('id', $miembros->pluck('user_id')->all())
            ->get(['id', 'nombre', 'email', 'admin', 'paqueteria'])
            ->keyBy('id');

        // La fila que autentica para cada email involucrado, en una sola consulta
        // en lugar de una por miembro.
        $idsDeLogin = User::whereIn('email', $usuarios->pluck('email')->filter()->unique())
            ->orderBy('id')
            ->get(['id', 'email'])
            ->groupBy('email')
            ->map(function ($filas) {
                return (int) $filas->first()->id;
            });

        $salida = $miembros->map(function ($m) use ($usuarios, $idsDeLogin) {
            $u = $usuarios->get((int) $m->user_id);
            $idLogin = $u ? ($idsDeLogin[$u->email] ?? null) : null;

            return [
                'id'               => $m->id,
                'user_id'          => (int) $m->user_id,
                'nombre'           => $u ? $u->nombreVisible() : null,
                'email'            => $u->email ?? null,
                'puesto'           => $m->puesto,
                'rol'              => $m->rol,
                'activo'           => (bool) $m->activo,
                'es_admin'         => (int) ($u->admin ?? 0) === 1,
                'ultima_actividad' => optional($m->ultima_actividad)->toIso8601String(),
                'en_linea'         => $m->enLinea(),
                // Diagnóstico del email duplicado.
                'es_cuenta_de_login' => $idLogin === null || $idLogin === (int) $m->user_id,
                'id_de_login'        => $idLogin,
            ];
        });

        return response()->json($salida->sortBy('nombre')->values()->all());
    }

    /**
     * Mueve el acceso a la cuenta que realmente autentica.
     *
     * Arregla en un clic el caso del email duplicado: da de baja la membresía que
     * quedó sobre la fila que no autentica y la crea (o reactiva) sobre la que sí,
     * conservando puesto y rol.
     *
     * La fila vieja se da de BAJA y no se borra: los emails de esta app mutan
     * (FacturaController@updateEmailLote), así que una fila que hoy no autentica
     * pudo haberlo hecho antes y tener mensajes a su nombre. Borrar la membresía
     * no borraría esos mensajes, pero sí dejaría su autor sin ficha en el
     * directorio.
     */
    public function moverALogin(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $login = User::cuentaDeLogin((string) $user->email);

        if (! $login) {
            return response()->json(['message' => 'No se encontró ninguna cuenta con ese email.'], 422);
        }

        if ((int) $login->id === (int) $user->id) {
            return response()->json([
                'message' => 'Esta cuenta ya es la que se usa al iniciar sesión: no hay nada que corregir.',
            ], 422);
        }

        if ($login->esPaqueteria()) {
            return response()->json([
                'message' => 'La cuenta que autentica es la cuenta compartida de portería, que no puede usarse en el chat. Creale una cuenta personal.',
            ], 422);
        }

        $vieja = MensajeriaMiembro::where('user_id', (int) $user->id)->first();

        DB::transaction(function () use ($vieja, $login) {
            $nueva = MensajeriaMiembro::where('user_id', (int) $login->id)->first()
                ?: new MensajeriaMiembro(['user_id' => (int) $login->id]);

            $nueva->user_id = (int) $login->id;
            $nueva->activo = true;

            // Se arrastran puesto y rol para no obligar a cargarlos de nuevo.
            if ($vieja) {
                $nueva->puesto = $nueva->puesto ?: $vieja->puesto;
                $nueva->rol = $vieja->rol;
            }

            $nueva->save();

            if ($vieja) {
                $vieja->activo = false;
                $vieja->save();
            }
        });

        return response()->json([
            'message' => "Acceso movido a la id {$login->id}, que es la cuenta con la que esa persona inicia sesión. Ahora sí va a ver el módulo.",
            'id_de_login' => (int) $login->id,
        ]);
    }

    /**
     * Busca cuentas existentes para habilitarlas.
     *
     * Devuelve UNA fila por email, no una por fila de `users`.
     *
     * Es la corrección de un problema que se comía el alta en silencio: como
     * `users.email` no tiene índice único, un mismo email puede tener varias
     * filas (hoy, cientos de casos, algunos con 8 filas). El buscador las
     * listaba todas, y al habilitar una que no era la del login se guardaba el
     * permiso sobre un id que ninguna sesión iba a tener nunca. La persona se
     * logueaba y no veía el módulo, sin ningún error en ninguna parte.
     *
     * Ahora el único id que se ofrece es el que autentica (User::cuentaDeLogin),
     * y los demás se informan como `duplicados` para que quien administra sepa
     * que ese email tiene un problema de datos, en vez de tener que adivinarlo.
     */
    public function buscar(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:150']);

        $q = $request->q;

        // 1) Los emails que coinciden. Se buscan los emails y no las filas para
        //    que el tope de resultados cuente personas y no duplicados: con un
        //    email de 8 filas, un límite de 20 filas daba 3 personas.
        $emails = User::where(function ($sub) use ($q) {
                $sub->where('email', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            })
            ->select('email')
            ->distinct()
            ->orderBy('email')
            ->limit(20)
            ->pluck('email');

        if ($emails->isEmpty()) {
            return response()->json([]);
        }

        // 2) TODAS las filas de esos emails. Completas y no sólo las que matchean,
        //    porque la fila del login puede no contener el texto buscado (si la
        //    búsqueda fue por nombre y esa fila lo tiene vacío) y hay que
        //    conocerla igual para no ofrecer la equivocada.
        $filas = User::whereIn('email', $emails)
            ->orderBy('email')
            ->orderBy('id')
            ->get(['id', 'nombre', 'email', 'admin', 'paqueteria'])
            ->groupBy('email');

        $membresias = MensajeriaMiembro::whereIn('user_id', $filas->flatten()->pluck('id')->all())
            ->get()
            ->keyBy('user_id');

        $salida = [];

        foreach ($filas as $email => $delEmail) {
            // Ordenadas por id: la primera es la que autentica. Es el mismo
            // criterio que User::cuentaDeLogin(), sin una consulta por email.
            $login = $delEmail->first();
            $otras = $delEmail->slice(1);

            $membresiaLogin = $membresias->get((int) $login->id);

            // Una membresía activa colgada de una fila que NO autentica: es el
            // permiso inerte. Se informa para poder corregirlo de un clic.
            $enOtra = $otras->first(function ($u) use ($membresias) {
                $m = $membresias->get((int) $u->id);

                return $m && $m->activo;
            });

            $salida[] = [
                'id'         => (int) $login->id,
                'nombre'     => $login->nombreVisible(),
                'email'      => $email,
                'es_admin'   => (int) $login->admin === 1,
                // Cuenta compartida de portería: el front la muestra deshabilitada
                // con el motivo, porque update() la va a rechazar igual.
                'compartida' => (bool) $login->paqueteria,
                'habilitado' => $membresiaLogin && (bool) $membresiaLogin->activo,
                // Cuántas filas de más tiene este email, y cuáles.
                'duplicados' => $otras->count(),
                'ids'        => $delEmail->pluck('id')->map('intval')->values()->all(),
                // Id de la fila mal habilitada, si hay una.
                'habilitado_en_otra' => $enOtra ? (int) $enOtra->id : null,
            ];
        }

        return response()->json($salida);
    }

    /**
     * Crea la cuenta de un empleado y lo habilita, en un paso.
     *
     * Es el camino habitual para el personal sin lote: no existe como usuario,
     * porque los usuarios de esta app se crean a partir de gastos comunes.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'   => 'required|string|max:150',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'puesto'   => 'nullable|string|max:80',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'email.unique'   => 'Ya existe un usuario con ese email. Buscalo y habilitalo en vez de crearlo.',
            'password.regex' => 'La contraseña debe tener al menos una mayúscula y un número.',
        ]);

        $user = new User();
        $user->nombre     = $datos['nombre'];
        $user->email      = $datos['email'];
        $user->password   = Hash::make($datos['password']);
        $user->admin      = 0;
        $user->paqueteria = false;
        $user->save();

        $miembro = MensajeriaMiembro::create([
            'user_id' => (int) $user->id,
            'puesto'  => $datos['puesto'] ?? null,
            'activo'  => true,
        ]);

        return response()->json([
            'message' => 'Cuenta creada y habilitada en la mensajería.',
            'miembro' => [
                'user_id' => (int) $user->id,
                'nombre'  => $user->nombre,
                'email'   => $user->email,
                'puesto'  => $miembro->puesto,
                'rol'     => $miembro->rol,
                'activo'  => true,
            ],
        ], 201);
    }

    /**
     * Habilita o deshabilita una cuenta existente, y ajusta puesto y rol.
     *
     * La baja es un flag y no un DELETE: el empleado que se va deja mensajes en
     * los canales, y borrar su ficha los dejaría sin nombre resoluble.
     */
    public function update(Request $request, $userId)
    {
        $datos = $request->validate([
            'activo' => 'required|boolean',
            'puesto' => 'nullable|string|max:80',
            'rol'    => ['nullable', Rule::in(['miembro', 'moderador'])],
        ]);

        $user = User::findOrFail($userId);

        // La cuenta de portería es compartida por el personal de turno: en un
        // chat, los mensajes quedarían sin autor identificable. El personal de
        // portería va con cuentas personales.
        if ($datos['activo'] && $user->esPaqueteria()) {
            return response()->json([
                'message' => 'La cuenta de portería es compartida por el personal de turno, así que no puede usarse en el chat: los mensajes quedarían sin autor. Creale una cuenta personal a cada empleado.',
            ], 422);
        }

        // Red de seguridad contra el permiso inerte: habilitar una fila que no es
        // la que autentica guarda el permiso y no hace nada. El buscador ya sólo
        // ofrece la correcta, así que acá se llega con una pantalla vieja o con un
        // request armado a mano; en los dos casos conviene decir qué id es el
        // bueno en vez de aceptar un alta que no va a funcionar.
        //
        // La BAJA sí se permite sobre cualquier fila: es lo que deja limpiar las
        // que quedaron mal habilitadas.
        if ($datos['activo'] && ! $user->esCuentaDeLogin()) {
            $login = User::cuentaDeLogin((string) $user->email);

            return response()->json([
                'message' => "Ese email tiene más de una cuenta en la base y ésta (id {$user->id}) no es la que se usa al iniciar sesión. Habilitá la id {$login->id}, que es la que autentica; si no, el permiso queda guardado pero la persona no ve el módulo.",
                'id_de_login' => (int) $login->id,
            ], 422);
        }

        $miembro = MensajeriaMiembro::where('user_id', (int) $user->id)->first();

        if (! $miembro) {
            $miembro = new MensajeriaMiembro();
            $miembro->user_id = (int) $user->id;
        }

        $miembro->activo = $datos['activo'];

        if (array_key_exists('puesto', $datos)) {
            $miembro->puesto = $datos['puesto'];
        }

        // `rol` se asigna explícito y no por fill() masivo: es un permiso.
        if (! empty($datos['rol'])) {
            $miembro->rol = $datos['rol'];
        }

        $miembro->save();

        return response()->json([
            'message' => $datos['activo']
                ? 'La cuenta ya tiene acceso a la mensajería interna.'
                : 'Se le quitó el acceso a la mensajería interna.',
            'miembro' => [
                'user_id' => (int) $user->id,
                'nombre'  => $user->nombre,
                'email'   => $user->email,
                'puesto'  => $miembro->puesto,
                'rol'     => $miembro->rol,
                'activo'  => (bool) $miembro->activo,
            ],
        ]);
    }

    /**
     * Resetea la contraseña de una cuenta de empleado.
     *
     * Sólo para cuentas creadas acá (sin lote): son las que no tienen un email
     * real detrás al que mandarle el link de recupero. A un empleado que además
     * es propietario se lo manda por el flujo normal de "olvidé mi contraseña".
     */
    public function resetPassword(Request $request, $userId)
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

        $user = User::findOrFail($userId);

        if (! MensajeriaMiembro::where('user_id', (int) $user->id)->exists()) {
            return response()->json([
                'message' => 'Esta cuenta no está dada de alta en la mensajería.',
            ], 422);
        }

        $user->password = Hash::make($datos['password']);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
