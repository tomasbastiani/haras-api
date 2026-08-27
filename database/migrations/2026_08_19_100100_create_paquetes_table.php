<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paquetes', function (Blueprint $table) {
            $table->id();

            // Referencia pública, sin valor secreto: es lo que se escribe en la
            // etiqueta que va pegada al paquete en el estante.
            $table->string('codigo', 12)->unique();

            // PIN de retiro. Va encriptado (cast 'encrypted' en el modelo): un
            // dump de la base no expone los PINes vigentes, pero la app puede
            // desencriptarlo para reenviarlo en un recordatorio.
            $table->text('pin');
            $table->unsignedTinyInteger('pin_intentos')->default(0);
            $table->timestamp('pin_bloqueado_at')->nullable();

            $table->string('nlote', 100);
            // Propietario resuelto al ingresar. Nullable a propósito: la relación
            // lote→email vive en gastoscomunes y tiene datos sucios. Si no matchea,
            // el paquete igual se registra y queda marcado para corregir.
            $table->integer('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            // Congelados al crear, mismo criterio que derivado_email en reclamos.
            $table->string('email_destino')->nullable();
            $table->string('destinatario')->nullable();

            $table->enum('correo', [
                'mercadolibre',
                'andreani',
                'oca',
                'correo_argentino',
                'urbano',
                'otro',
            ])->default('otro');
            $table->string('tracking')->nullable();
            $table->enum('tipo', ['sobre', 'caja_chica', 'caja_grande', 'bulto'])->default('caja_chica');

            // Sin esto la oficina no encuentra el paquete cuando el vecino llega.
            $table->string('ubicacion')->nullable();
            $table->text('observaciones')->nullable();

            $table->enum('estado', ['recibido', 'retirado', 'devuelto', 'vencido'])->default('recibido');

            $table->integer('recibido_por');
            $table->foreign('recibido_por')->references('id')->on('users');
            $table->timestamp('recibido_at')->nullable();
            $table->timestamp('notificado_at')->nullable();
            $table->timestamp('recordatorio_at')->nullable();
            $table->timestamp('retirado_at')->nullable();

            $table->timestamps();

            $table->index(['estado', 'nlote']);
            $table->index(['user_id', 'estado']);
            $table->index('tracking');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paquetes');
    }
};
