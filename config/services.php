<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        // Token del servidor de Postmark, para su API (lista de suprimidas). En
        // SMTP de Postmark el usuario ES ese token, así que si no hay uno
        // propio se usa MAIL_USERNAME y prod no necesita una variable nueva.
        'token' => env('POSTMARK_TOKEN', env('MAIL_USERNAME')),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        // effort controla profundidad de razonamiento y gasto de tokens.
        // "low" alcanza de sobra para clasificar en 8 categorías y mantiene
        // la latencia del chat baja.
        'effort' => env('ANTHROPIC_EFFORT', 'low'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2048),
        'timeout' => env('ANTHROPIC_TIMEOUT', 60),
    ],

    'reclamos' => [
        // Se muestra en el chat ante una emergencia.
        'telefono_guardia' => env('RECLAMOS_TELEFONO_GUARDIA', ''),
        // Destinatario cuando la categoría no tiene mail configurado.
        'email_fallback' => env('RECLAMOS_EMAIL_FALLBACK'),
    ],

];
