<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cancha extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'activa',
        'orden',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }
}
