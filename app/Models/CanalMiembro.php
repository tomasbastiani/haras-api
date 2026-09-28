<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Participación de un usuario en un canal, y su cursor de lectura.
 *
 * Tener fila acá es lo ÚNICO que da acceso a los mensajes de un canal (la sola
 * excepción es el admin leyendo grupos: ver Canal::puedeLeer()).
 */
class CanalMiembro extends Model
{
    protected $table = 'mensajeria_canal_miembros';

    protected $fillable = ['canal_id', 'user_id', 'rol', 'ultimo_leido_mensaje_id', 'silenciado'];

    protected $casts = ['silenciado' => 'boolean'];

    public function canal()
    {
        return $this->belongsTo(Canal::class, 'canal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mensajes sin leer de este miembro en este canal.
     *
     * Se cuenta contra el cursor y se excluye lo propio: los mensajes que uno
     * mismo acaba de mandar no son novedad, y contarlos deja el badge prendido
     * apenas escribís.
     */
    public function noLeidos(): int
    {
        return Mensaje::where('canal_id', $this->canal_id)
            ->where('id', '>', (int) $this->ultimo_leido_mensaje_id)
            ->where('user_id', '!=', $this->user_id)
            ->whereNull('eliminado_at')
            ->count();
    }

    /**
     * Adelanta el cursor de lectura.
     *
     * Sólo hacia adelante: con dos pestañas abiertas, la que quedó mirando un
     * mensaje viejo mandaría un cursor anterior y volvería a marcar como no
     * leído lo que ya se leyó en la otra.
     */
    public function marcarLeido(int $mensajeId): void
    {
        if ($mensajeId > (int) $this->ultimo_leido_mensaje_id) {
            $this->ultimo_leido_mensaje_id = $mensajeId;
            $this->save();
        }
    }
}
