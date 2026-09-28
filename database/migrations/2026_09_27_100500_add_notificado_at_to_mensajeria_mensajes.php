<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca de "ya se mandó la push por este mensaje".
     *
     * Hace falta porque las push no salen al postear: las junta el comando
     * mensajeria:notificar, que corre cada minuto. Sin esta marca, cada corrida
     * volvería a notificar todo lo que siga sin leer, y quien se fue a almorzar
     * dejando un mensaje sin abrir recibiría la misma push una vez por minuto.
     *
     * Es una marca por mensaje y no por destinatario: la corrida notifica de una
     * vez a todos los que corresponde, así que después de eso el mensaje ya no
     * tiene nada pendiente. El que lo leyó dentro del minuto de gracia
     * simplemente no entró en esa tanda, porque su cursor de lectura ya había
     * pasado el mensaje.
     */
    public function up()
    {
        Schema::table('mensajeria_mensajes', function (Blueprint $table) {
            $table->timestamp('notificado_at')->nullable()->after('eliminado_at');

            // El índice que usa el comando para encontrar trabajo: los mensajes
            // sin notificar son un puñado, pero la tabla crece sin techo.
            $table->index(['notificado_at', 'id']);
        });
    }

    public function down()
    {
        Schema::table('mensajeria_mensajes', function (Blueprint $table) {
            $table->dropIndex(['notificado_at', 'id']);
            $table->dropColumn('notificado_at');
        });
    }
};
