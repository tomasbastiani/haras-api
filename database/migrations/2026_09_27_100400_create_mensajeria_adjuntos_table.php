<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archivos e imágenes colgados de un mensaje.
     *
     * Tabla aparte y no columnas en `mensajeria_mensajes` porque un mensaje puede
     * llevar varios: el caso real es el de mantenimiento sacando tres fotos de lo
     * mismo, y con columnas fijas eso serían `adjunto1_path`, `adjunto2_path`…
     *
     * Los archivos NO van al disco `public` —a diferencia de los de
     * ArchivoController, que quedan servidos por URL directa bajo /storage—.
     * Van al disco `local`, que no es accesible por HTTP, y se sirven por un
     * endpoint que chequea Canal::puedeLeer(). En un chat privado, una URL
     * adivinable que devuelve la foto de una conversación ajena vaciaría de
     * sentido todo el control de acceso de los mensajes.
     */
    public function up()
    {
        Schema::create('mensajeria_adjuntos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mensaje_id');
            $table->foreign('mensaje_id')->references('id')->on('mensajeria_mensajes');

            // Ruta relativa dentro del disco `local`. No es una URL: el archivo
            // sólo sale por el endpoint autenticado.
            $table->string('path', 255);

            // Con el que lo subieron, para poder devolvérselo con ese nombre.
            // El nombre en disco es aleatorio, así que este no se usa para
            // resolver rutas (evita cualquier traversal desde el nombre).
            $table->string('nombre_original', 255);

            $table->string('mime', 100);
            $table->unsignedInteger('tamano');

            // Sólo en imágenes. Sirve para que el hilo reserve el lugar antes de
            // que la imagen cargue: sin esto, cada foto que entra empuja la
            // conversación y te mueve el texto que estabas leyendo.
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('mensaje_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensajeria_adjuntos');
    }
};
