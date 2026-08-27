<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reclamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nlote',
        'categoria',
        'urgencia',
        'ubicacion_tipo',
        'ubicacion_detalle',
        'resumen',
        'descripcion',
        'estado',
        'derivado_a',
        'derivado_email',
        'tomado_por',
        'tomado_at',
        'resuelto_at',
        'nota_cierre',
    ];

    protected $casts = [
        'tomado_at' => 'datetime',
        'resuelto_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tomadoPor()
    {
        return $this->belongsTo(User::class, 'tomado_por');
    }

    public function scopeAbiertos($query)
    {
        return $query->whereIn('estado', ['nuevo', 'tomado']);
    }

    public function esEmergencia(): bool
    {
        return $this->urgencia === 'emergencia';
    }

    public function ubicacionLegible(): string
    {
        return $this->ubicacion_tipo === 'lote'
            ? "Lote {$this->ubicacion_detalle}"
            : $this->ubicacion_detalle;
    }
}
