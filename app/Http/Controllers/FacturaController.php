<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturaController extends Controller
{
    public function buscarPorDni($email)
    {
        $query = DB::connection('hsm')
            ->table('gastoscomunes')
            ->where('email', $email)
            ->orderBy('numero', 'desc');

        $facturas = $query->get();

        return response()->json($facturas);
    }

    public function listarTodos()
    {
        $facturas = DB::connection('hsm')
            ->table('gastoscomunes')
            ->select('*')
            ->orderBy('numero', 'desc')
            ->get();

        return response()->json($facturas);
    }

    public function obtenerPeriodos()
    {
        $periodos = DB::connection('hsm')
            ->table('gastoscomunes')
            ->select('numero')
            ->distinct()
            ->orderByDesc('numero')
            ->get();

        return response()->json($periodos);
    }

    public function agregarGasto(Request $request)
    {
        $validated = $request->validate([
            'numero' => 'required|numeric',
        ]);

        $numero = $validated['numero'];

        // Paso 1: Obtener pares únicos (email, nlote)
        $usuarios = DB::connection('hsm')
            ->table('gastoscomunes')
            ->select('email', 'nlote')
            ->groupBy('email', 'nlote')
            ->get();

        $insertData = [];

        foreach ($usuarios as $usuario) {
            $nlote = $usuario->nlote;

            $existe = DB::connection('hsm')->table('gastoscomunes')->where([
                ['email', '=', $usuario->email],
                ['nlote', '=', $nlote],
                ['numero', '=', $numero],
            ])->exists();

            if (!$existe) {
                $carta = "https://harassantamaria.com.ar/gcomunes/$numero/Cartas/L-$nlote.pdf";
                $gastocomun = "https://harassantamaria.com.ar/gcomunes/$numero/LiqGastosComunes{$numero}.pdf";

                $insertData[] = [
                    'email' => $usuario->email,
                    'nlote' => $nlote,
                    'numero' => $numero,
                    'carta' => $carta,
                    'gastocomun' => $gastocomun,
                ];
            }
        }

        if (!empty($insertData)) {
            DB::connection('hsm')->table('gastoscomunes')->insert($insertData);
        }

        return response()->json([
            'message' => 'Gasto común agregado correctamente para todos los usuarios.',
            'insertados' => count($insertData),
        ]);
    }

    public function eliminarPorPeriodo($numero)
    {
        DB::connection('hsm')
            ->table('gastoscomunes')
            ->where('numero', $numero)
            ->delete();

        return response()->json(['message' => "Periodo $numero eliminado correctamente"]);
    }

    public function updateGasto(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:gastoscomunes,id',
            'numero' => 'required|numeric',
            'email' => 'required|email',
            'nlote' => 'required|string',
            'carta' => 'required|url',
            'gastocomun' => 'required|url',
        ]);

        try {
            DB::connection('hsm')->table('gastoscomunes')
                ->where('id', $validated['id'])
                ->update([
                    'numero' => $validated['numero'],
                    'email' => $validated['email'],
                    'nlote' => $validated['nlote'],
                    'carta' => $validated['carta'],
                    'gastocomun' => $validated['gastocomun'],
                ]);

            return response()->json(['message' => 'Factura actualizada correctamente.']);
        } catch (\Exception $e) {
            Log::error('Error actualizando gasto:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al actualizar la factura.'], 500);
        }
    }

    public function getLotesPorEmail($email)
    {
        $query = DB::table('gastoscomunes')
            ->select('email', 'nlote')
            ->distinct();

        if ($email !== 'admin@hsm.com') {
            $query->where('email', $email);
        }

        $lotes = $query
            ->orderByRaw('CAST(nlote AS UNSIGNED) ASC')
            ->get();

        return response()->json($lotes);
    }

    public function updateEmailLote(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'nlote' => 'required'
        ]);

        try {
            DB::table('gastoscomunes')
                ->where('nlote', $request->nlote)
                ->update(['email' => $request->email]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar el email.'], 500);
        }
    }

    // public function createUserIfNotExists(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required|string|min:4'
    //     ]);

    //     $exists = DB::table('users')->where('email', $request->email)->exists();

    //     Log::info('exists', array($exists));

    //     if ($exists) {
    //         return response()->json(['success' => false, 'message' => 'El usuario ya existe.'], 400);
    //     }

    //     try {
    //         DB::table('users')->insert([
    //             'email' => $request->email,
    //             'password' => $request->password,
    //             'created_at' => now(),
    //             'updated_at' => now()
    //         ]);

    //         return response()->json(['success' => true]);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'message' => 'Error al crear el usuario.'], 500);
    //     }
    // }

    public function createUserIfNotExists(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:4'
        ]);

        $exists = DB::table('users')->where('email', $request->email)->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'El usuario ya existe.'], 400);
        }

        try {
            DB::table('users')->insert([
                'nombre' => $request->nombre,
                'email' => $request->email,
                'password' => $request->password,
                'admin' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error al crear usuario', [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario.'
            ], 500);
        }
    }

    public function verificarEmail($email)
    {
        $exists = DB::table('users')->where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }

    public function obtenerEmailsPorLote()
    {
        try {
            $resultados = DB::table('gastoscomunes')
                ->select('email', 'nlote')
                ->distinct()
                ->orderByRaw('CAST(nlote AS UNSIGNED) ASC')
                ->get();

            return response()->json($resultados);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los datos: ' . $e->getMessage()], 500);
        }
    }

    public function getLotesPorUser($email)
    {
        try {
            $lotes = DB::table('gastoscomunes')
                ->select('nlote')
                ->where('email', $email)
                ->distinct()
                ->orderByRaw('CAST(nlote AS UNSIGNED) ASC')
                ->get();

            return response()->json($lotes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCartas()
    {
        try {
            $cartas = DB::table('gastoscomunes')
                ->select('numero')
                ->distinct()
                ->orderByRaw('CAST(numero AS UNSIGNED) ASC')
                ->get();

            return response()->json($cartas);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    
}
