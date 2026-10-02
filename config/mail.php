<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses",
    |            "postmark", "log", "array", "failover"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream de Postmark para envíos masivos
    |--------------------------------------------------------------------------
    |
    | Los mails masivos (aviso de gastos comunes, mail personalizado) salen por
    | el stream "broadcast" de Postmark y no por el transaccional. Dos razones:
    |
    | - En broadcast Postmark agrega solo el link de baja al pie y los headers
    |   List-Unsubscribe de un clic que exigen Gmail y Yahoo a los envíos
    |   masivos. Es lo que más baja las quejas de spam.
    | - Si un envío masivo sale mal y Postmark frena ese stream, los mails
    |   transaccionales (reset de contraseña, turnos) siguen saliendo.
    |
    | Quien se da de baja deja de recibir SÓLO lo que sale por broadcast.
    | Vacío = no se manda el header y todo sale por el stream por defecto.
    |
    */

    'stream_masivo' => env('MAIL_STREAM_MASIVO', 'broadcast'),

    /*
    |--------------------------------------------------------------------------
    | Envíos masivos encolados
    |--------------------------------------------------------------------------
    |
    | El comando `envios:procesar` corre cada minuto y manda hasta `por_minuto`
    | mails por pasada. Con 600 destinatarios y 50 por minuto, un aviso tarda
    | unos 12 minutos en salir entero: a cambio, nunca se corta por timeout.
    | Un destinatario que falla se reintenta en las pasadas siguientes hasta
    | `max_intentos`, y después queda en estado `error`.
    |
    */

    'masivos' => [
        'por_minuto'   => (int) env('MAIL_MASIVOS_POR_MINUTO', 50),
        'max_intentos' => (int) env('MAIL_MASIVOS_MAX_INTENTOS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
