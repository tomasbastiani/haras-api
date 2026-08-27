<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReclamoRuteo extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria',
        'nombre',
        'email',
        'user_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Ruteo para una categoría, con fallback a administración si la categoría
     * no está configurada o quedó inactiva.
     */
    public static function paraCategoria(string $categoria): ?self
    {
        return static::where('categoria', $categoria)->where('activo', true)->first()
            ?? static::where('categoria', 'administracion')->where('activo', true)->first();
    }
}
