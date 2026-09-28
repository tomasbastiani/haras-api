<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una conversación de la mensajería interna: directo (1 a 1) o grupo.
 *
 * Acá vive la regla de visibilidad del módulo, en un solo lugar a propósito:
 * cualquier endpoint que devuelva mensajes tiene que pasar por puedeLeer() /
 * puedeEscribir(), y no repetir la condición a mano.
 */
class Canal extends Model
{
    protected $table = 'mensajeria_canales';

    protected $fillable = ['tipo', 'nombre', 'descripcion', 'clave_directo', 'creado_por', 'archivado'];

    protected $casts = [
        'archivado'         => 'boolean',
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function miembros()
    {
        return $this->hasMany(CanalMiembro::class, 'canal_id');
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class, 'canal_id');
    }

    public function esDirecto(): bool
    {
        return $this->tipo === 'directo';
    }

    public function esGrupo(): bool
    {
        return $this->tipo === 'grupo';
    }

    /**
     * Clave canónica de un directo entre dos usuarios: "menor-mayor".
     *
     * Ordenar los ids es todo el truco: hace que el par (A,B) y el par (B,A)
     * produzcan la misma clave, y el índice único de la columna convierte el
     * directo duplicado en un imposible en lugar de en un bug a descubrir.
     */
    public static function claveDirecto(int $unUserId, int $otroUserId): string
    {
        $ids = [$unUserId, $otroUserId];
        sort($ids);

        return $ids[0] . '-' . $ids[1];
    }

    public function tieneMiembro(int $userId): bool
    {
        return $this->miembros()->where('user_id', $userId)->exists();
    }

    /**
     * ¿Puede este usuario leer el canal?
     *
     * Regla del módulo, decidida explícitamente:
     *
     *   - Miembro del canal: sí, siempre.
     *   - Admin de la app en un GRUPO: sí, aunque no sea miembro (supervisión de
     *     los canales de trabajo del personal).
     *   - Admin de la app en un DIRECTO: NO. Nunca. Si el módulo se llama "chat
     *     privado", los mensajes privados entre dos empleados no los lee un
     *     tercero, y menos como efecto lateral de tener otro rol.
     *
     * Por eso la condición está acá y no repartida por los controladores: es la
     * clase de regla que se filtra si hay que recordarla en ocho endpoints.
     */
    public function puedeLeer(User $user): bool
    {
        if ($this->tieneMiembro((int) $user->id)) {
            return true;
        }

        return $this->esGrupo() && (int) $user->admin === 1;
    }

    /**
     * ¿Puede escribir en el canal?
     *
     * Sólo los miembros. El admin que mira un grupo del que no participa lo ve
     * en sólo lectura: es supervisión, no una cuenta con voz en toda la empresa.
     * Un mensaje suyo en un canal al que nadie lo agregó no se distinguiría de
     * una intromisión.
     */
    public function puedeEscribir(User $user): bool
    {
        return ! $this->archivado && $this->tieneMiembro((int) $user->id);
    }

    /** True si el usuario entra sólo por su rol de admin y no participa. */
    public function esSupervision(User $user): bool
    {
        return ! $this->tieneMiembro((int) $user->id) && $this->puedeLeer($user);
    }
}
