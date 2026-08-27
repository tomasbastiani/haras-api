<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de la conversación con el bot.
     *
     * La API de Claude es stateless: en cada mensaje hay que reenviar todo el
     * hilo. Guardamos los bloques de contenido tal cual los devuelve la API
     * (incluidos los thinking blocks, que deben reenviarse sin modificar).
     */
    public function up()
    {
        Schema::create('chat_conversaciones', function (Blueprint $table) {
            $table->id();
            // users.id es int(11) signed (tabla legacy).
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->enum('estado', ['activa', 'cerrada'])->default('activa');
            $table->longText('mensajes')->nullable();
            $table->unsignedBigInteger('reclamo_id')->nullable();
            $table->foreign('reclamo_id')->references('id')->on('reclamos');
            $table->timestamps();

            $table->index(['user_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_conversaciones');
    }
};
