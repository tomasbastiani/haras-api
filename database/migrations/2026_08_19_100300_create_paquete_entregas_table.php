<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El acta de entrega. Tabla aparte y 1:1 con el paquete a propósito: un acta
     * no se edita nunca, y separarla del registro operativo (que sí cambia de
     * estado) deja claro qué es dato de trabajo y qué es constancia.
     */
    public function up()
    {
        Schema::create('paquete_entregas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paquete_id')->unique();
            $table->foreign('paquete_id')->references('id')->on('paquetes');

            $table->string('folio', 30)->unique();

            // pin: el que retiró dictó el PIN que le llegó al titular.
            // manual: no hubo PIN (sin señal, sin celular, PIN bloqueado). Es
            // evidencia más débil y se muestra como tal en la bandeja.
            $table->enum('metodo', ['pin', 'manual']);
            $table->string('motivo_manual', 300)->nullable();

            $table->enum('retirado_por', ['titular', 'autorizado', 'otro']);
            $table->string('nombre');
            // Número de documento solamente. Foto del DNI NO se guarda: suma
            // exposición de datos personales y no aporta sobre firma + acuse.
            $table->string('dni', 20)->nullable();

            // Rutas en disco privado, servidas por ruta autenticada.
            $table->string('firma_path')->nullable();
            $table->string('foto_path')->nullable();

            $table->integer('operario_id');
            $table->foreign('operario_id')->references('id')->on('users');
            $table->timestamp('entregado_at')->nullable();

            // Acuse de recibo del titular desde su propia sesión. Es la prueba
            // más fuerte del expediente: no la emite la oficina, la emite el
            // dispositivo autenticado del propietario.
            $table->enum('ack_estado', ['pendiente', 'confirmado', 'desconocido', 'tacito'])
                ->default('pendiente');
            $table->timestamp('ack_at')->nullable();
            $table->integer('ack_user_id')->nullable();
            $table->foreign('ack_user_id')->references('id')->on('users');
            $table->string('ack_ip', 45)->nullable();

            $table->timestamps();

            $table->index('ack_estado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paquete_entregas');
    }
};
