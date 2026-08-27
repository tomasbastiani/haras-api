<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente mínimo de la Messages API de Claude.
 *
 * Se usa el facade Http (Guzzle) en vez del SDK oficial de PHP porque el SDK
 * requiere PHP >= 8.1 y el entorno local corre 8.0.15. Guzzle ya es dependencia
 * del proyecto, así que esto no agrega nada al composer.json.
 */
class ClaudeService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * @param  array  $system    Bloques de system prompt (con cache_control).
     * @param  array  $messages  Hilo completo de la conversación.
     * @param  array  $tools     Definiciones de herramientas.
     * @return array             Respuesta cruda de la API, ya decodificada.
     */
    public function mensaje(array $system, array $messages, array $tools = []): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            throw new RuntimeException('Falta ANTHROPIC_API_KEY en el .env');
        }

        $payload = [
            'model'      => config('services.anthropic.model', 'claude-opus-5'),
            'max_tokens' => (int) config('services.anthropic.max_tokens', 2048),
            // El thinking queda activo (es el default en Opus 5). Desactivarlo
            // acelera un poco, pero el modelo pasa a escribir a veces la llamada
            // a la tool como texto plano en lugar de emitirla: el usuario lee
            // "listo, registré tu reclamo" y el ticket nunca se creó. Para bajar
            // latencia se usa effort, no se apaga el thinking.
            'thinking'      => ['type' => 'adaptive'],
            'output_config' => ['effort' => config('services.anthropic.effort', 'low')],
            'system'        => $system,
            'messages'      => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])
            ->timeout((int) config('services.anthropic.timeout', 60))
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException('La API respondió ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Concatena los bloques de texto de una respuesta.
     */
    public function texto(array $respuesta): string
    {
        $partes = [];

        foreach ($respuesta['content'] ?? [] as $bloque) {
            if (($bloque['type'] ?? null) === 'text' && ! empty($bloque['text'])) {
                $partes[] = $bloque['text'];
            }
        }

        return trim(implode("\n\n", $partes));
    }

    /**
     * Devuelve el primer bloque tool_use de la respuesta, o null.
     */
    public function toolUse(array $respuesta): ?array
    {
        foreach ($respuesta['content'] ?? [] as $bloque) {
            if (($bloque['type'] ?? null) === 'tool_use') {
                return $bloque;
            }
        }

        return null;
    }

    /**
     * Los clasificadores de seguridad pueden rechazar un pedido: la API devuelve
     * HTTP 200 con stop_reason "refusal" y content vacío o parcial. Hay que
     * chequearlo antes de leer el contenido.
     */
    public function fueRechazado(array $respuesta): bool
    {
        return ($respuesta['stop_reason'] ?? null) === 'refusal';
    }
}
