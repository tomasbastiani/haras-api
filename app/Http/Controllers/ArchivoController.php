<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchivoController extends Controller
{
    public function index()
    {
        return DB::table('archivos')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nlote' => 'required|integer',
            'ncarta' => 'required|integer',
            'user'  => 'required|string',
            'file'  => 'required|file|max:2048',
        ], [
            'file.max' => 'El archivo supera el tamaño máximo permitido de 2MB.',
        ]);

        // Guardar archivo físico en storage/public/archivos
        $path = $request->file('file')->store('archivos', 'public');

        // Insert directo en la BD
        $id = DB::table('archivos')->insertGetId([
            'nlote'      => $request->nlote,
            'ncarta'     => $request->ncarta,
            'user'       => $request->user,
            'comments'   => $request->comments,
            'file_path'  => "/storage/" . $path,
            'file_name'  => $request->file('file')->getClientOriginalName(),
            'mime_type'  => $request->file('file')->getMimeType(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Retornar el registro insertado
        return response()->json(DB::table('archivos')->find($id), 201);
    }

    public function destroy($id)
    {
        $archivo = DB::table('archivos')->find($id);

        if (!$archivo) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        // Eliminar archivo físico
        Storage::disk('public')->delete(str_replace('/storage/', '', $archivo->file_path));

        // Eliminar registro en la BD
        DB::table('archivos')->where('id', $id)->delete();

        return response()->json(['message' => 'Archivo eliminado']);
    }

    public function download($id)
    {
        $a = DB::table('archivos')->find($id);
        if (!$a) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        // Convierte "/storage/..." -> "..."
        $filePath = str_replace('/storage/', '', $a->file_path);

        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json(['message' => 'El archivo no existe en el servidor'], 404);
        }

        // Devolver con nombre original (forza descarga)
        return Storage::disk('public')->download($filePath, $a->file_name);
    }

    public function indexByUser(Request $request, $user)
    {
        $query = DB::table('archivos')
            ->orderBy('created_at', 'desc');

        // Si NO es admin, filtramos por usuario
        if (! $request->boolean('is_admin')) {
            $query->where('user', $user);
        }

        $archivos = $query->get();

        return response()->json($archivos);
    }


}
