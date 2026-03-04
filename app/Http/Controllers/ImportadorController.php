<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportadorController extends Controller
{
    public function importarGastos(Request $request)
    {
        try {

            Log::info('Inicio importación gastos comunes');

            $request->validate([
                'file' => 'required|mimes:xlsx,xls'
            ]);

            $file = $request->file('file');

            if (!$file) {
                return response()->json([
                    'message' => 'No se recibió archivo'
                ], 400);
            }

            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) <= 1) {
                return response()->json([
                    'message' => 'El archivo está vacío o no tiene datos válidos'
                ], 400);
            }

            DB::beginTransaction();

            DB::table('gastoscomunes_notificaciones')->delete();

            $insertData = [];

            foreach ($rows as $index => $row) {

                if ($index === 0) continue;

                if (empty($row[0]) || empty($row[1]) || empty($row[2])) continue;

                $insertData[] = [
                    'email'      => trim($row[0]),
                    'nombre'     => trim($row[1]),
                    'nlote'      => trim($row[2]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($insertData)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No hay registros válidos'
                ], 400);
            }

            DB::table('gastoscomunes_notificaciones')->insert($insertData);

            DB::commit();

            Log::info('Importación exitosa', [
                'cantidad' => count($insertData)
            ]);

            return response()->json([
                'message' => 'Importación realizada correctamente',
                'cantidad' => count($insertData),
                'data' => DB::table('gastoscomunes_notificaciones')->get()
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Error en importación', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Error al importar',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }


    public function importarMorosos(Request $request)
    {
        try {

            Log::info('Inicio importación morosos');

            $request->validate([
                'file' => 'required|mimes:xlsx,xls'
            ]);

            $file = $request->file('file');

            if (!$file) {
                return response()->json([
                    'message' => 'No se recibió archivo'
                ], 400);
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) <= 1) {
                return response()->json([
                    'message' => 'El archivo está vacío o no tiene datos válidos'
                ], 400);
            }

            DB::beginTransaction();

            DB::table('morosos')->delete();

            $insertData = [];

            foreach ($rows as $index => $row) {

                if ($index === 0) continue;

                if (empty($row[0]) || empty($row[1]) || empty($row[2]) || empty($row[3])) continue;

                $insertData[] = [
                    'email'      => trim($row[0]),
                    'nombre'     => trim($row[1]),
                    'nlote'      => trim($row[2]),
                    'monto'      => floatval($row[3]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($insertData)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No hay registros válidos'
                ], 400);
            }

            DB::table('morosos')->insert($insertData);

            DB::commit();

            return response()->json([
                'message' => 'Importación realizada correctamente',
                'cantidad' => count($insertData),
                'data' => DB::table('morosos')->get()
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Error en importación morosos', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Error al importar',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    public function obtenerGastos()
    {
        return response()->json(
            DB::table('gastoscomunes_notificaciones')
                ->orderBy('id', 'desc')
                ->get()
        );
    }

    public function obtenerMorosos()
    {
        return response()->json(
            DB::table('morosos')
                ->orderBy('id', 'desc')
                ->get()
        );
    }

}