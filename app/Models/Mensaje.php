<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un mensaje de la mensajería interna.
 *
 * A diferencia de PaqueteEvento, acá SÍ se puede editar y borrar: es una
 * conversación de trabajo, no una constancia sellada. El borrado deja lápida
 * (`eliminado_at`) para poder mostrar "mensaje eliminado" en el hueco.
 */
class Mensaje extends Model
{
    protected $table = 'mensajeria_mensajes';

    protected $fillable = ['canal_id', 'user_id', 'tipo', 'cuerpo', 'responde_a_id'];

    protected $casts = [
        'editado_at'  => 'datetime',
        'eliminado_at' => 'datetime',
    ];

    /** Tope de largo. Generoso, pero evita que un pegado accidental infle la tabla. */
    const LARGO_MAX = 4000;

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class, 'canal_id');
    }

    public function adjuntos()
    {
        return $this->hasMany(Adjunto::class, 'mensaje_id');
    }

    public function estaEliminado(): bool
    {
        return $this->eliminado_at !== null;
    }

    public function esDeSistema(): bool
    {
        return $this->tipo === 'sistema';
    }

    /**
     * Sólo el autor edita o borra lo suyo. Un mensaje de sistema no se toca, y
     * el admin tampoco: poder reescribir lo que dijo otro en un chat privado es
     * exactamente lo que haría que nadie confíe en el módulo.
     */
    public function puedeModificar(User $user): bool
    {
        return ! $this->esDeSistema()
            && ! $this->estaEliminado()
            && (int) $this->user_id === (int) $user->id;
    }

    /**
     * Forma en la que el mensaje sale a la API.
     *
     * Centralizado acá y no en cada controlador porque de esto depende que el
     * cuerpo de un mensaje borrado NO viaje al cliente: si la lápida se
     * serializara con el modelo entero, "eliminado" sería nada más que un tachón
     * en la UI y el texto seguiría llegando en el JSON.
     */
    public function paraApi(): array
    {
        if ($this->estaEliminado()) {
            // Sin `adjuntos` tampoco: al borrar el mensaje los archivos se
            // eliminan del disco, así que listarlos sólo daría ids que devuelven
            // 404. La lápida no deja nada que pedir.
            return [
                'id'           => $this->id,
                'canal_id'     => $this->canal_id,
                'user_id'      => (int) $this->user_id,
                'tipo'         => $this->tipo,
                'cuerpo'       => null,
                'eliminado'    => true,
                'editado'      => false,
                'responde_a_id' => null,
                'adjuntos'     => [],
                'created_at'   => optional($this->created_at)->toIso8601String(),
            ];
        }

        return [
            'id'            => $this->id,
            'canal_id'      => $this->canal_id,
            'user_id'       => (int) $this->user_id,
            'tipo'          => $this->tipo,
            'cuerpo'        => $this->cuerpo,
            'eliminado'     => false,
            'editado'       => $this->editado_at !== null,
            'responde_a_id' => $this->responde_a_id,
            'adjuntos'      => $this->adjuntos->map(function ($a) {
                return $a->paraApi();
            })->values()->all(),
            'created_at'    => optional($this->created_at)->toIso8601String(),
        ];
    }
}
