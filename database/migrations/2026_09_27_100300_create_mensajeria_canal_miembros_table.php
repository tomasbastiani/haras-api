<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién participa de cada canal, y hasta dónde leyó.
     *
     * Es la tabla que decide la visibilidad: tener fila acá es lo único que da
     * acceso a los mensajes de un canal. La única excepción es el admin de la
     * app sobre los GRUPOS, y es de sólo lectura (ver Canal::puedeLeer()).
     */
    public function up()
    {
        Schema::create('mensajeria_canal_miembros', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('canal_id');
            $table->foreign('canal_id')->references('id')->on('mensajeria_canales');

            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users');

            $table->enum('rol', ['owner', 'miembro'])->default('miembro');

            /**
             * Cursor de lectura: id del último mensaje que este usuario vio.
             *
             * Los no leídos se CUENTAN contra este cursor (id > cursor), no se
             * guardan en un contador que se incrementa y decrementa. Un contador
             * se desincroniza siempre —dos pestañas abiertas, un push que llega
             * tarde, un request que falla a mitad— y queda un badge rojo que no
             * se va nunca. El cursor no puede desincronizarse: es una sola
             * escritura idempotente.
             *
             * De paso sale gratis la línea de "mensajes nuevos" del hilo.
             *
             * Sin FK a propósito: es una marca de posición, no una referencia.
             * Con FK, borrar el mensaje justo apuntado fallaría o dejaría el
             * cursor en null (= todo sin leer de nuevo).
             */
            $table->unsignedBigInteger('ultimo_leido_mensaje_id')->nullable();

            // Silenciar corta las push, no el acceso: el canal se sigue viendo.
            $table->boolean('silenciado')->default(false);

            $table->timestamps();

            // Impide la membresía duplicada, que se traduciría en mensajes
            // contados dos veces y en la persona apareciendo dos veces en la
            // lista del canal.
            $table->unique(['canal_id', 'user_id']);

            // Armar el sidebar de un usuario: sus canales, de un solo golpe.
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensajeria_canal_miembros');
    }
};
