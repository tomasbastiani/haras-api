<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién tiene acceso a la mensajería interna, y su ficha dentro del módulo.
     *
     * Va como tabla aparte y no como columna en `users` —al contrario de
     * `users.paqueteria`— porque acá el permiso es ADITIVO: no define qué es la
     * cuenta. Un empleado puede ser además propietario con lote, gastos comunes
     * y turnero; entrar a la mensajería no le cambia el resto de la app. Es el
     * mismo criterio que `paqueteria_operarios`.
     *
     * La clave es `user_id`, NO el email: en esta app los emails mutan
     * (FacturaController@updateEmailLote existe justamente para eso). Si el
     * acceso colgara del email, corregirle el mail a alguien le cortaría el
     * acceso en silencio, y reasignar ese email a otra persona le heredaría las
     * conversaciones.
     *
     * Además de permiso, esta tabla es el DIRECTORIO del módulo: es la lista de
     * gente con la que se puede abrir un chat, y la que sostiene la presencia.
     */
    public function up()
    {
        Schema::create('mensajeria_miembros', function (Blueprint $table) {
            $table->id();

            // users.id es int(11) signed (tabla legacy): la FK tiene que ser
            // integer() y no el unsignedBigInteger que pone Laravel por defecto.
            $table->integer('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users');

            // Cargo visible en el directorio ("Portería", "Administración").
            // Es informativo: no otorga ningún permiso.
            $table->string('puesto', 80)->nullable();

            // `moderador` puede crear canales y administrar sus miembros. No da
            // acceso a leer conversaciones ajenas.
            $table->enum('rol', ['miembro', 'moderador'])->default('miembro');

            // La baja es un flag y no un DELETE: al empleado que se va le
            // quedan mensajes en los canales, y borrar la fila dejaría esos
            // mensajes sin autor resoluble en el directorio.
            $table->boolean('activo')->default(true);

            // La toca el endpoint /mensajeria/sync, que ya corre igual: da la
            // presencia ("en línea") sin ninguna escritura extra dedicada.
            $table->timestamp('ultima_actividad')->nullable();

            $table->timestamps();

            $table->index(['activo', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensajeria_miembros');
    }
};
