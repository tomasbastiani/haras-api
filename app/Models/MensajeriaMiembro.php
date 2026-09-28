<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Acceso a la mensajería interna + ficha del empleado dentro del módulo.
 *
 * Permiso ADITIVO sobre una cuenta existente, igual que PaqueteriaOperario y al
 * contrario de users.paqueteria: el empleado que además es propietario conserva
 * intacto todo el resto de la app.
 */
class MensajeriaMiembro extends Model
{
    protected $table = 'mensajeria_miembros';

    /**
     * `rol` queda fuera de $fillable a propósito (mismo criterio que
     * users.paqueteria): se asigna explícito desde el controlador de admin, para
     * que ningún update() masivo pueda repartir moderadores por accidente.
     */
    protected $fillable = ['user_id', 'puesto', 'activo'];

    protected $casts = [
        'activo'           => 'boolean',
        'ultima_actividad' => 'datetime',
    ];

    /** Minutos sin tocar /sync tras los cuales se deja de considerar "en línea". */
    const MINUTOS_PRESENCIA = 3;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ¿Este usuario tiene acceso al módulo?
     *
     * Lo consulta el middleware en CADA request, no sólo en el login: las
     * sesiones de esta app son eternas por diseño (useAuth.js), así que validar
     * una única vez al entrar le dejaría el acceso vivo a alguien dado de baja
     * hasta que cerrara sesión por su cuenta.
     */
    public static function esMiembro(int $userId): bool
    {
        return static::where('user_id', $userId)->where('activo', true)->exists();
    }

    public static function esModerador(int $userId): bool
    {
        return static::where('user_id', $userId)
            ->where('activo', true)
            ->where('rol', 'moderador')
            ->exists();
    }

    /** Presencia derivada de la última vez que el cliente pidió /sync. */
    public function enLinea(): bool
    {
        return $this->ultima_actividad
            && $this->ultima_actividad->gt(now()->subMinutes(self::MINUTOS_PRESENCIA));
    }
}
