<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvioMasivoDestinatario extends Model
{
    public const PENDIENTE = 'pendiente';
    public const ENVIADO   = 'enviado';
    public const ERROR     = 'error';
    // No se le manda: email rebotado (marcado en users o bloqueado en Postmark).
    public const OMITIDO   = 'omitido';
    // No se le manda: ya lo recibió en un envío anterior del mismo aviso.
    public const YA_RECIBIDO = 'ya_recibido';
    // Frenado por el admin; "Reanudar" lo vuelve a pendiente.
    public const PAUSADO   = 'pausado';
    // Frenado por el admin para no seguir; no se retoma.
    public const CANCELADO = 'cancelado';

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
