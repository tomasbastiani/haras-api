<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo o imagen colgado de un mensaje.
 *
 * Vive en el disco `local`, que no se sirve por HTTP: el único camino hacia el
 * archivo es MensajeController@adjunto, que chequea Canal::puedeLeer().
 */
class Adjunto extends Model
{
    protected $table = 'mensajeria_adjuntos';

    // Sin updated_at: un adjunto no se edita, se borra con su mensaje.
    public $timestamps = false;

    protected $fillable = [
        'mensaje_id', 'path', 'nombre_original', 'mime', 'tamano', 'ancho', 'alto',
    ];

    protected $casts = [
        'tamano'     => 'integer',
        'ancho'      => 'integer',
        'alto'       => 'integer',
        'created_at' => 'datetime',
    ];

    public function mensaje()
    {
        return $this->belongsTo(Mensaje::class, 'mensaje_id');
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * Forma en la que sale a la API.
     *
     * No incluye `path`: la ruta en disco no le sirve de nada al cliente y
     * publicarla sólo invita a probar si hay alguna forma de pedirla directo. El
     * cliente pide el adjunto por id.
     */
    public function paraApi(): array
    {
        return [
            'id'      => $this->id,
            'nombre'  => $this->nombre_original,
            'mime'    => $this->mime,
            'tamano'  => (int) $this->tamano,
            'imagen'  => $this->esImagen(),
            'ancho'   => $this->ancho,
            'alto'    => $this->alto,
        ];
    }

    /** Borra el archivo del disco. Se llama al eliminar el mensaje. */
    public function borrarArchivo(): void
    {
        if ($this->path && Storage::disk('local')->exists($this->path)) {
            Storage::disk('local')->delete($this->path);
        }
    }
}
