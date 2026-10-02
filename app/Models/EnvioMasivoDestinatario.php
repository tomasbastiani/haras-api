<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvioMasivoDestinatario extends Model
{
    public const PENDIENTE = 'pendiente';
    public const ENVIADO   = 'enviado';
    public const ERROR     = 'error';
    // No se le manda: email marcado como rebotado.
    public const OMITIDO   = 'omitido';

    protected $table = 'envios_masivos_destinatarios';

    protected $guarded = ['id'];

    protected $casts = [
        'enviado_at' => 'datetime',
    ];

    public function envio()
    {
        return $this->belongsTo(EnvioMasivo::class, 'envio_masivo_id');
    }
}
