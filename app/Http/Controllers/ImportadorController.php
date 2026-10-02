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
            // Valores crudos, sin formatear: hacen falta para saber si el CVU
            // vino como texto o como número (ver validarCvu).
            $rawRows = $sheet->toArray(null, true, false);

            if (count($rows) <= 1) {
                return response()->json([
                    'message' => 'El archivo está vacío o no tiene datos válidos'
                ], 400);
            }

            // Columnas: A email, B nombre, C lote, D CVU, E alias. D y E son
            // opcionales; si vienen, tienen que ser válidas. Se valida todo
            // ANTES de borrar la tabla, así un archivo con errores no deja la
            // lista a medio cargar ni vacía.
            $insertData = [];
            $errores = [];

            foreach ($rows as $index => $row) {

                if ($index === 0) continue;

                // Vacío de verdad, no empty(): empty("0") es true en PHP, y eso
                // descartaba en silencio la fila del Lote 0.
                if ($this->celdaVacia($row[0] ?? null) || $this->celdaVacia($row[1] ?? null) || $this->celdaVacia($row[2] ?? null)) continue;

                $filaExcel = $index + 1;

                $cvu = $this->validarCvu($rawRows[$index][3] ?? null, $filaExcel, $errores);
                $alias = $this->validarAlias($row[4] ?? null, $filaExcel, $errores);

                $insertData[] = [
                    'email'      => trim($row[0]),
                    'nombre'     => trim($row[1]),
                    'nlote'      => trim($row[2]),
                    'cvu'        => $cvu,
                    'alias'      => $alias,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($errores)) {
                return response()->json([
                    'message' => 'El archivo tiene errores. No se importó nada; la lista anterior sigue igual.',
                    'errores' => $errores,
                ], 422);
            }

            if (empty($insertData)) {
                return response()->json([
                    'message' => 'No hay registros válidos'
                ], 400);
            }

            DB::beginTransaction();

            DB::table('gastoscomunes_notificaciones')->delete();

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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


    /**
     * Plantilla .xlsx para /import-gastos.
     *
     * Lo importante no es el encabezado sino el formato: Lote, CVU y Alias van
     * preformateadas como Texto ("@"), así lo que el admin tipee o pegue ahí se
     * guarda tal cual. Un CVU escrito en una celda "General" Excel lo convierte
     * en número y le redondea los últimos dígitos (ver validarCvu).
     *
     * Sin fila de ejemplo a propósito: el importador lee todo lo que está debajo
     * del encabezado, y un ejemplo olvidado terminaría recibiendo el mail. Las
     * indicaciones van en una segunda hoja, que el importador no lee (lee sólo
     * la hoja activa, y se guarda con la primera activa).
     */
    public function plantillaGastos()
    {
        $filasConFormato = 1000;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Destinatarios');
        $hoja->fromArray(['Email', 'Nombre', 'Lote', 'CVU', 'Alias'], null, 'A1');
        $hoja->getStyle('A1:E1')->getFont()->setBold(true);
        $hoja->getStyle('A1:E1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFD100');
        $hoja->freezePane('A2');

        $hoja->getStyle("C2:E{$filasConFormato}")->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        foreach (['A' => 32, 'B' => 28, 'C' => 10, 'D' => 26, 'E' => 22] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        $ayuda = $spreadsheet->createSheet();
        $ayuda->setTitle('Instrucciones');
        $ayuda->fromArray([
            ['Cómo completar la hoja "Destinatarios"'],
            [''],
            ['• Una fila por lote. Si un vecino tiene varios lotes, va una fila por cada uno con el mismo email.'],
            ['• Email, Nombre y Lote son obligatorios. Las filas a las que les falte alguno se ignoran.'],
            ['• CVU (opcional): 22 dígitos. Se aceptan espacios o guiones, se limpian solos.'],
            ['• Alias (opcional): de 6 a 20 caracteres (letras, números, punto o guion).'],
            ['• No cambies el formato de las columnas Lote, CVU y Alias: están en Texto para que Excel no redondee el CVU.'],
            ['  Si pegás datos de otra planilla, usá "Pegar sólo valores" para no traer el formato de origen.'],
            ['• No borres la fila de encabezado: el importador siempre saltea la primera fila.'],
            ['• El import REEMPLAZA la lista completa: el archivo tiene que tener a todos los destinatarios.'],
        ], null, 'A1');
        $ayuda->getStyle('A1')->getFont()->setBold(true);
        $ayuda->getColumnDimension('A')->setWidth(110);

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, 'plantilla-gastos-comunes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function celdaVacia($valor): bool
    {
        return $valor === null || trim((string) $valor) === '';
    }

    /**
     * CVU/CBU de la columna D: vacío → null; si viene, 22 dígitos exactos.
     *
     * Se rechaza si la celda es numérica y no texto. Excel guarda los números
     * con 15 dígitos significativos, así que un CVU tipeado en una celda
     * "General" pierde los últimos 7 y queda redondeado: sigue teniendo 22
     * dígitos, pero es OTRO CVU, y el vecino le transferiría a una cuenta
     * equivocada. No hay forma de recuperar los dígitos perdidos, sólo de
     * detectarlo y pedir la columna formateada como Texto.
     */
    private function validarCvu($valor, int $filaExcel, array &$errores): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            $errores[] = "Fila {$filaExcel}: el CVU está cargado como número y Excel le redondea los últimos dígitos. Formateá la columna D como Texto y volvé a escribir el CVU.";
            return null;
        }

        $cvu = preg_replace('/[\s\-]/', '', (string) $valor);

        if (!preg_match('/^\d{22}$/', $cvu)) {
            $errores[] = "Fila {$filaExcel}: el CVU \"{$valor}\" no es válido (tiene que tener 22 dígitos).";
            return null;
        }

        return $cvu;
    }

    /**
     * Alias de la columna E: vacío → null; si viene, 6 a 20 caracteres entre
     * letras, números, punto y guion (regla del BCRA).
     */
    private function validarAlias($valor, int $filaExcel, array &$errores): ?string
    {
        $alias = trim((string) ($valor ?? ''));

        if ($alias === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9.\-]{6,20}$/', $alias)) {
            $errores[] = "Fila {$filaExcel}: el alias \"{$alias}\" no es válido (6 a 20 caracteres: letras, números, punto o guion).";
            return null;
        }

        return $alias;
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