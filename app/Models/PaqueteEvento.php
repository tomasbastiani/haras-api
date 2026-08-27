<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora sellada de paquetes.
 *
 * Alcance real del sellado, para no sobrevender lo que hace: garantiza que
 * nadie con acceso a la base pueda alterar o borrar un evento sin que la
 * verificación lo detecte, porque la clave del HMAC vive en el .env y no en
 * la base. No protege contra quien tenga además esa clave (el servidor
 * comprometido entero), y para eso haría falta anclar el hash de cabecera
 * en un tercero. Para el uso que le vamos a dar, alcanza y sobra.
 */
class PaqueteEvento extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'paquete_id',
        'tipo',
        'nota',
        'user_id',
        'payload',
        'hash_anterior',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Hash de arranque de la cadena. */
    public const HASH_GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function paquete()
    {
        return $this->belongsTo(Paquete::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Los datos extra del evento, tal como quedaron sellados. */
    public function datos(): array
    {
        return json_decode($this->payload, true)['datos'] ?? [];
    }

    /**
     * Registra un evento y lo encadena al último de la tabla.
     *
     * Va dentro de una transacción con lockForUpdate porque dos entregas
     * simultáneas leerían el mismo evento anterior y armarían dos ramas con el
     * mismo hash_anterior, rompiendo la cadena. El volumen acá es de decenas de
     * eventos por día, así que serializar no cuesta nada.
     */
    public static function registrar(
        int $paqueteId,
        string $tipo,
        ?string $nota = null,
        ?int $userId = null,
        array $datos = []
    ): self {
        return DB::transaction(function () use ($paqueteId, $tipo, $nota, $userId, $datos) {
            $anterior = static::query()->lockForUpdate()->orderByDesc('id')->first();
            $hashAnterior = $anterior->hash ?? self::HASH_GENESIS;

            $creadoEn = now();

            $payload = static::armarPayload($paqueteId, $tipo, $nota, $userId, $datos, $creadoEn);

            return static::create([
                'paquete_id'    => $paqueteId,
                'tipo'          => $tipo,
                'nota'          => $nota,
                'user_id'       => $userId,
                'payload'       => $payload,
                'hash_anterior' => $hashAnterior,
                'hash'          => static::calcularHash($hashAnterior, $payload),
                'created_at'    => $creadoEn,
            ]);
        });
    }

    /**
     * JSON canónico del evento. El orden de las claves es parte del contrato:
     * cambiarlo invalidaría la verificación de todo el historial ya sellado.
     */
    public static function armarPayload(
        int $paqueteId,
        string $tipo,
        ?string $nota,
        ?int $userId,
        array $datos,
        $creadoEn
    ): string {
        return json_encode([
            'paquete_id' => $paqueteId,
            'tipo'       => $tipo,
            'nota'       => $nota,
            'user_id'    => $userId,
            'datos'      => $datos,
            'created_at' => $creadoEn->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function calcularHash(string $hashAnterior, string $payload): string
    {
        return hash_hmac('sha256', $hashAnterior . '|' . $payload, (string) config('paqueteria.hash_key'));
    }

    /**
     * Verifica que este evento no haya sido alterado: que el hash cierre y que
     * las columnas consultables sigan coincidiendo con el payload sellado.
     *
     * @return string[] Lista de problemas encontrados (vacía si está sano).
     */
    public function problemas(?string $hashEsperadoAnterior = null): array
    {
        $fallas = [];

        if ($hashEsperadoAnterior !== null && $this->hash_anterior !== $hashEsperadoAnterior) {
            $fallas[] = 'no engancha con el evento anterior (falta o se reordenó un registro)';
        }

        if (static::calcularHash($this->hash_anterior, $this->payload) !== $this->hash) {
            $fallas[] = 'el hash no corresponde al contenido sellado';
        }

        $sellado = json_decode($this->payload, true);

        if (! is_array($sellado)) {
            $fallas[] = 'el payload sellado no es JSON válido';

            return $fallas;
        }

        $columnas = [
            'paquete_id' => (int) $this->paquete_id,
            'tipo'       => $this->tipo,
            'nota'       => $this->nota,
            'user_id'    => $this->user_id === null ? null : (int) $this->user_id,
        ];

        foreach ($columnas as $campo => $valor) {
            if (($sellado[$campo] ?? null) !== $valor) {
                $fallas[] = "la columna '{$campo}' no coincide con el payload sellado";
            }
        }

        return $fallas;
    }
}
