<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora sellada de todo lo que le pasa a un paquete. Es la que alimenta
     * el timeline en la app y la que sostiene la constancia de entrega.
     *
     * Cada fila encadena con la anterior (hash_anterior → hash) sobre TODA la
     * tabla, no por paquete: así también se detecta la desaparición completa
     * del historial de un paquete, no sólo la edición de una fila.
     *
     * El hash es un HMAC con clave fuera de la base (config paqueteria.hash_key).
     * Esto es lo que hace que el sello sirva: con un sha256 pelado, cualquiera
     * con acceso a la base podría editar una fila y recalcular la cadena hacia
     * adelante. Sin la clave, no puede.
     */
    public function up()
    {
        Schema::create('paquete_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paquete_id');
            $table->foreign('paquete_id')->references('id')->on('paquetes');

            // ingreso | notificado | recordatorio | entregado | ack_confirmado
            // ack_desconocido | ack_tacito | pin_fallido | devuelto | vencido
            $table->string('tipo', 40);
            $table->string('nota', 500)->nullable();
            $table->integer('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            // JSON canónico exacto sobre el que se calculó el hash. Se guarda
            // literal (no se re-serializa al verificar) porque cualquier cambio
            // de orden de claves o de formato de fecha daría un hash distinto y
            // haría fallar la verificación de una cadena sana.
            //
            // Las columnas de arriba repiten datos que están acá adentro: sirven
            // para consultar; el payload es el registro sellado. El verificador
            // compara ambos, así editar sólo la columna también se detecta.
            $table->longText('payload');

            $table->char('hash_anterior', 64);
            $table->char('hash', 64)->unique();

            $table->timestamp('created_at')->nullable();

            $table->index(['paquete_id', 'id']);
            $table->index('tipo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paquete_eventos');
    }
};
