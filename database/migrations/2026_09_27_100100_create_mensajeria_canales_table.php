<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversaciones: mensajes directos (1 a 1) y grupos, en la MISMA tabla.
     *
     * Un directo es un canal de dos miembros sin nombre. Se unifica porque todo
     * lo que viene después —mensajes, cursor de lectura, no leídos, adjuntos,
     * push— es idéntico en los dos casos: separarlos obligaría a duplicar cada
     * query y cada endpoint para no ganar nada.
     *
     * La diferencia que sí importa es de VISIBILIDAD, y se resuelve con `tipo`:
     * un admin de la app puede leer cualquier grupo (supervisión), pero NUNCA un
     * directo. Ver Canal::puedeLeer().
     */
    public function up()
    {
        Schema::create('mensajeria_canales', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo', ['directo', 'grupo']);

            // Null en los directos: el nombre a mostrar es el del otro
            // participante, y depende de quién mira.
            $table->string('nombre', 80)->nullable();
            $table->string('descripcion', 255)->nullable();

            /**
             * Deduplicación de directos: "menorUserId-mayorUserId".
             *
             * Sin esto, "abrir chat con Juan" dos veces crea dos hilos paralelos
             * con la mitad de los mensajes en cada uno —el bug clásico de los
             * DMs, y uno que no se puede arreglar después sin fusionar
             * conversaciones a mano. El índice único lo hace imposible.
             *
             * Null en los grupos (MySQL permite múltiples NULL en un UNIQUE, así
             * que no colisionan entre sí).
             */
            $table->string('clave_directo', 40)->nullable()->unique();

            $table->integer('creado_por')->nullable();
            $table->foreign('creado_por')->references('id')->on('users');

            // Los grupos se archivan, no se borran: sus mensajes son el
            // historial de trabajo del equipo.
            $table->boolean('archivado')->default(false);

            // Fecha del último mensaje. Redundante contra mensajes.created_at,
            // pero es lo que ordena la lista de conversaciones: sin esto, armar
            // el sidebar pide un MAX() por canal en cada refresco del polling.
            $table->timestamp('ultimo_mensaje_at')->nullable();

            $table->timestamps();

            $table->index(['tipo', 'archivado']);
            $table->index('ultimo_mensaje_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensajeria_canales');
    }
};
