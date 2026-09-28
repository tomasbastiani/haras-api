<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes
    |--------------------------------------------------------------------------
    */

    // Tope de caracteres por mensaje.
    'largo_max' => (int) env('MENSAJERIA_LARGO_MAX', 4000),

    // Cuántos mensajes trae cada página del hilo (se pagina hacia atrás).
    'pagina' => (int) env('MENSAJERIA_PAGINA', 50),

    // Tope de miembros de un grupo. No es una limitación técnica: es para que
    // "grupo" siga siendo un equipo y no una lista de difusión encubierta, que
    // es donde el costo de las push se vuelve un problema.
    'grupo_max_miembros' => (int) env('MENSAJERIA_GRUPO_MAX', 50),

    /*
    |--------------------------------------------------------------------------
    | Adjuntos
    |--------------------------------------------------------------------------
    |
    | Los archivos van al disco `local` (no accesible por HTTP) y se sirven por
    | un endpoint autenticado, nunca por URL directa: ver la migración de
    | mensajeria_adjuntos.
    |
    */
    'adjuntos' => [

        // Tope por archivo, en KB. El límite real lo pone igual php.ini
        // (upload_max_filesize / post_max_size), así que subir esto sin tocar
        // el ini no cambia nada.
        'max_kb' => (int) env('MENSAJERIA_ADJUNTO_MAX_KB', 8192),

        'max_por_mensaje' => (int) env('MENSAJERIA_ADJUNTOS_MAX', 5),

        // Lista blanca. Deliberadamente sin formatos ejecutables ni comprimidos:
        // esto es un chat de trabajo, no un canal de distribución de binarios.
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'],

        // Las imágenes se reescalan a este lado máximo antes de guardarse. Una
        // foto de celular son 4 MB y 4000px de ancho, y en el hilo se ve a 300px:
        // guardar el original sólo hace que la conversación tarde en cargar y se
        // coma el disco.
        'imagen_max_px' => (int) env('MENSAJERIA_IMAGEN_MAX_PX', 1600),

        'imagen_calidad' => (int) env('MENSAJERIA_IMAGEN_CALIDAD', 82),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiempo real (polling)
    |--------------------------------------------------------------------------
    |
    | No hay websockets: este proyecto corre PHP 8.0 + Laravel 9 en hosting
    | compartido, donde no se puede levantar un demonio (Reverb pide PHP 8.2 y
    | Laravel 11). El cliente consulta /mensajeria/sync con estos intervalos, que
    | el backend le informa en /bootstrap para poder ajustarlos sin recompilar la
    | PWA —que es lo que en la práctica cuesta más desplegar.
    |
    | La cadencia es adaptativa: el front frena cuando la pestaña no se ve.
    |
    */
    'sync' => [
        'activo_ms'   => (int) env('MENSAJERIA_SYNC_ACTIVO', 4000),   // pestaña visible
        'fondo_ms'    => (int) env('MENSAJERIA_SYNC_FONDO', 15000),   // visible pero sin interactuar
        'inactivo_ms' => (int) env('MENSAJERIA_SYNC_INACTIVO', 45000), // varios minutos quieto
    ],

    // Minutos sin pedir /sync tras los cuales el punto verde se apaga.
    'presencia_minutos' => (int) env('MENSAJERIA_PRESENCIA_MINUTOS', 3),

    /*
    |--------------------------------------------------------------------------
    | Badge del navbar
    |--------------------------------------------------------------------------
    |
    | Cada cuánto refresca el contador de no leídos que se ve en el navbar, en ms.
    |
    | Es MUCHO más lento que el polling de adentro del módulo, y tiene que serlo:
    | esto corre en todas las pantallas del portal, no sólo en el chat. Un minuto
    | alcanza para enterarse de que llegó algo; los cuatro segundos sólo hacen
    | falta cuando estás mirando la conversación.
    |
    | Sólo lo piden los usuarios habilitados, y se detiene con la pestaña oculta
    | y mientras el módulo está abierto (ahí el /sync ya trae el número).
    |
    */
    'badge_ms' => (int) env('MENSAJERIA_BADGE_MS', 60000),

    /*
    |--------------------------------------------------------------------------
    | Notificaciones push
    |--------------------------------------------------------------------------
    |
    | Las push NO se mandan al postear el mensaje. FcmService::send() hace un
    | POST HTTP secuencial por cada token y la cola está en `sync`, así que
    | notificar a un grupo de 20 personas dentro del request dejaría el envío de
    | un mensaje colgado veinte llamadas HTTP.
    |
    | El plan es juntarlas en un comando del scheduler (fase 2, todavía no
    | existe). El minuto de gracia es lo que hace que valga la pena: si la
    | persona ya leyó el mensaje en la app, cuando corra el comando su cursor de
    | lectura habrá avanzado y no se le manda nada.
    |
    */
    'push_gracia_minutos' => (int) env('MENSAJERIA_PUSH_GRACIA', 1),

    // A dónde lleva el tap en la notificación. No sale de APP_URL porque en este
    // proyecto suele quedar en localhost, y una push que abre localhost no sirve.
    'push_url' => env('MENSAJERIA_PUSH_URL', 'https://harassantamaria.com.ar/mensajeria'),

];
