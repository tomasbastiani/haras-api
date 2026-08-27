<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversacion extends Model
{
    use HasFactory;

    protected $table = 'chat_conversaciones';

    protected $fillable = [
        'user_id',
        'estado',
        'mensajes',
        'reclamo_id',
    ];

    protected $casts = [
        'mensajes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reclamo()
    {
        return $this->belongsTo(Reclamo::class);
    }

    /**
     * Historial en el formato que espera la API (array de mensajes).
     */
    public function hilo(): array
    {
        return $this->mensajes ?? [];
    }

    public function agregar(string $rol, $contenido): void
    {
        $hilo = $this->hilo();
        $hilo[] = ['role' => $rol, 'content' => $contenido];
        $this->mensajes = $hilo;
    }
}
