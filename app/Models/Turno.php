<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $fillable = [
        'cancha_id',
        'user_id',
        'nlote',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'cancelado_at',
        'cancelado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cancelado_at' => 'datetime',
    ];

    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canceladoPor()
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'reservado');
    }
}
