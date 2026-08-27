<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Acta de entrega. Se crea una sola vez y no se edita: lo único que cambia
 * después es el acuse del titular, que es un hecho posterior y propio.
 */
class PaqueteEntrega extends Model
{
    protected $fillable = [
        'paquete_id',
        'folio',
        'metodo',
        'motivo_manual',
        'retirado_por',
        'nombre',
        'dni',
        'firma_path',
        'foto_path',
        'operario_id',
        'entregado_at',
        'ack_estado',
        'ack_at',
        'ack_user_id',
        'ack_ip',
    ];

    protected $casts = [
        'entregado_at' => 'datetime',
        'ack_at'       => 'datetime',
    ];

    /** La ruta interna del archivo no viaja al cliente; se sirve por endpoint. */
    protected $hidden = ['firma_path', 'foto_path'];

    public function paquete()
    {
        return $this->belongsTo(Paquete::class);
    }

    public function operario()
    {
        return $this->belongsTo(User::class, 'operario_id');
    }

    public function esManual(): bool
    {
        return $this->metodo === 'manual';
    }

    public function ackPendiente(): bool
    {
        return $this->ack_estado === 'pendiente';
    }

    /**
     * Fuerza probatoria del acta, para mostrarla tal cual es en la bandeja en
     * vez de dar a entender que todas las entregas valen lo mismo.
     */
    public function solidez(): string
    {
        if ($this->ack_estado === 'desconocido') {
            return 'impugnada';
        }

        if ($this->metodo === 'pin' && $this->ack_estado === 'confirmado') {
            return 'alta';
        }

        if ($this->metodo === 'pin') {
            return 'media';
        }

        return 'baja';
    }

    public static function generarFolio(int $id): string
    {
        return 'HSM-PAQ-' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
