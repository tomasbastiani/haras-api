<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clave de sellado de la bitácora
    |--------------------------------------------------------------------------
    |
    | Con esta clave se firma cada evento de paquete_eventos (HMAC-SHA256). Es
    | lo que impide que alguien con acceso a la base edite un evento y recalcule
    | la cadena para tapar el rastro.
    |
    | Cae a APP_KEY si no se define, pero conviene una propia: rotar APP_KEY por
    | cualquier otro motivo invalidaría la verificación de todo el historial.
    | Una vez en producción, esta clave NO se cambia.
    |
    */
    'hash_key' => env('PAQUETERIA_HASH_KEY') ?: env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Acuse de recibo
    |--------------------------------------------------------------------------
    |
    | Horas que tiene el titular para confirmar o desconocer una entrega antes
    | de que quede como confirmación tácita. Se registra como 'tacito', nunca
    | como 'confirmado': disfrazar un silencio de confirmación expresa es
    | exactamente lo que haría perder credibilidad al expediente.
    |
    */
    'ack_horas' => (int) env('PAQUETERIA_ACK_HORAS', 48),

    /*
    |--------------------------------------------------------------------------
    | Retiro
    |--------------------------------------------------------------------------
    */

    // Intentos de PIN fallidos antes de bloquearlo. Al bloquearse, la entrega
    // sólo puede cerrarse por método manual, que queda marcado como tal.
    'pin_intentos_max' => (int) env('PAQUETERIA_PIN_INTENTOS', 5),

    // Días sin retirar antes de mandar recordatorio y de marcar como vencido.
    'dias_recordatorio' => (int) env('PAQUETERIA_DIAS_RECORDATORIO', 7),
    'dias_vencimiento' => (int) env('PAQUETERIA_DIAS_VENCIMIENTO', 30),

];
