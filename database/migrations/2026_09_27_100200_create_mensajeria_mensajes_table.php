<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los mensajes.
     *
     * IMPORTANTE — esto NO es una bitácora sellada como `paquete_eventos`, y no
     * hay que copiarle el patrón. Aquella es append-only y encadenada por HMAC
     * porque sostiene una constancia de entrega frente a un reclamo. Esto es una
     * conversación privada de trabajo: la gente se equivoca, corrige y borra, y
     * negarle eso sólo haría que el módulo no se use.
     *
     * Por eso el borrado es lápida (`eliminado_at`) y no DELETE: hay que poder
     * mostrar "mensaje eliminado" en el hueco, porque un mensaje que desaparece
     * sin dejar rastro rompe el hilo de la conversación para los demás.
     */
    public function up()
    {
        Schema::create('mensajeria_mensajes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('canal_id');
            $table->foreign('canal_id')->references('id')->on('mensajeria_canales');

            // Autor. Sale SIEMPRE del token de Sanctum en el controlador, nunca
            // de un campo del request (misma convención que FCM, reclamos y
            // paquetería).
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users');

            // `sistema` son los avisos generados por la app ("X agregó a Y al
            // canal"): se muestran centrados y en gris, no tienen autor real ni
            // se pueden editar.
            $table->enum('tipo', ['texto', 'archivo', 'sistema'])->default('texto');

            $table->text('cuerpo');

            // Respuesta a otro mensaje. La columna se crea desde el principio
            // aunque la UI de hilos venga después: agregarla ahora es gratis, y
            // así los mensajes ya escritos también se pueden citar cuando llegue.
            $table->unsignedBigInteger('responde_a_id')->nullable();

            $table->timestamp('editado_at')->nullable();
            $table->timestamp('eliminado_at')->nullable();

            $table->timestamps();

            // El índice que sostiene todo: traer el hilo de un canal paginado
            // hacia atrás por id, y el barrido del endpoint /sync (id > cursor).
            $table->index(['canal_id', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensajeria_mensajes');
    }
};
