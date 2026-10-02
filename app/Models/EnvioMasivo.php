<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un envío masivo de mail encolado (aviso de gastos comunes o mail
 * personalizado). Lo procesa `envios:procesar`; ver EnviosMasivosService.
 */
class EnvioMasivo extends Model
{
    public const TIPO_GASTOS_COMUNES = 'gastos_comunes';
    public const TIPO_PERSONALIZADO  = 'personalizado';

    protected $table = 'envios_masivos';

    protected $guarded = ['id'];

    protected $casts = [
        'finalizado_at' => 'datetime',
    ];

    public function destinatarios()
    {
        return $this->hasMany(EnvioMasivoDestinatario::class, 'envio_masivo_id');
    }

    /**
     * Conteo por estado, para mostrar el progreso en el front.
     */
    public function resumen(): array
    {
        $porEstado = $this->destinatarios()
            ->selectRaw('estado, COUNT(*) as n')
            ->groupBy('estado')
            ->pluck('n', 'estado');

        return [
            'id'            => $this->id,
            'tipo'          => $this->tipo,
            'periodo'       => $this->periodo,
            'asunto'        => $this->asunto,
            'total'         => $this->total,
            'pendientes'    => (int) ($porEstado['pendiente'] ?? 0),
            'enviados'      => (int) ($porEstado['enviado'] ?? 0),
            'errores'       => (int) ($porEstado['error'] ?? 0),
            'omitidos'      => (int) ($porEstado['omitido'] ?? 0),
            'finalizado'    => $this->finalizado_at !== null,
            'finalizado_at' => optional($this->finalizado_at)->toIso8601String(),
            'creado_at'     => optional($this->created_at)->toIso8601String(),
        ];
    }
}
