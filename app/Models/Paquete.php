<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paquete extends Model
{
    use HasFactory;

    protected $table = 'paquetes';

    protected $fillable = [
        'codigo',
        'pin',
        'pin_intentos',
        'pin_bloqueado_at',
        'nlote',
        'user_id',
        'email_destino',
        'destinatario',
        'correo',
        'tracking',
        'tipo',
        'ubicacion',
        'observaciones',
        'estado',
        'recibido_por',
        'recibido_at',
        'notificado_at',
        'recordatorio_at',
        'retirado_at',
    ];

    protected $casts = [
        // El PIN nunca se guarda en claro: un dump de la base no sirve para
        // retirar paquetes ajenos. Va encriptado y no hasheado porque hay que
        // poder releerlo para reenviarlo en el recordatorio de los 7 días.
        'pin'              => 'encrypted',
        'pin_bloqueado_at' => 'datetime',
        'recibido_at'      => 'datetime',
        'notificado_at'    => 'datetime',
        'recordatorio_at'  => 'datetime',
        'retirado_at'      => 'datetime',
    ];

    /**
     * El PIN sale del modelo sólo cuando alguien lo pide explícitamente: si se
     * serializa un paquete a JSON, no viaja. Evita filtrarlo en la bandeja del
     * operario, que es justamente quien no tiene que verlo.
     */
    protected $hidden = ['pin'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recibidoPor()
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function entrega()
    {
        return $this->hasOne(PaqueteEntrega::class);
    }

    public function eventos()
    {
        return $this->hasMany(PaqueteEvento::class)->orderBy('id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'recibido');
    }

    /** Lotes cuyo email en gastoscomunes no matcheó ningún usuario. */
    public function scopeSinPropietario($query)
    {
        return $query->whereNull('user_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'recibido';
    }

    public function pinBloqueado(): bool
    {
        return $this->pin_bloqueado_at !== null;
    }

    public function verificarPin(string $intento): bool
    {
        // hash_equals para no filtrar información por tiempo de comparación.
        return hash_equals((string) $this->pin, trim($intento));
    }

    public function diasEnEspera(): int
    {
        return (int) ($this->recibido_at?->diffInDays(now()) ?? 0);
    }

    /**
     * PIN de retiro: 6 dígitos. Numérico y no palabra a propósito — se dicta en
     * voz alta sin ambigüedad de ortografía, acentos ni mayúsculas, y se tipea
     * rápido en el teclado numérico de la oficina.
     */
    public static function generarPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Referencia pública del paquete, la que se escribe en la etiqueta del
     * estante. No es secreta: el secreto es el PIN.
     *
     * Alfabeto sin 0/O/1/I/L para que nadie transcriba mal a mano.
     */
    public static function generarCodigo(): string
    {
        $alfabeto = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

        do {
            $codigo = 'P-';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
