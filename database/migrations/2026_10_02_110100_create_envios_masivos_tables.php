<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos masivos de mail encolados: una fila por envío y una por destinatario.
 *
 * Antes el aviso de gastos comunes y el mail personalizado mandaban todo dentro
 * del request, en un loop. Con cientos de destinatarios el request se cortaba
 * por timeout a mitad de camino, y al reintentar, los primeros recibían el mail
 * dos veces. Ahora el request sólo crea estas filas y el comando
 * `envios:procesar` (scheduler, cada minuto) manda de a tandas.
 *
 * Dos llaves contra duplicados:
 * - `clave` única por envío: el mismo aviso (p. ej. gastos del período 212)
 *   no se puede encolar dos veces sin pedirlo explícitamente.
 * - (envio_masivo_id, email) única: dentro de un envío, nadie recibe dos.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('envios_masivos', function (Blueprint $table) {
            $table->id();
            // gastos_comunes | personalizado
            $table->string('tipo', 30);
            $table->string('clave', 100)->unique();
            $table->string('periodo', 100)->nullable();
            $table->string('asunto', 255)->nullable();
            // Plantilla del mail personalizado, con placeholders sin reemplazar:
            // se arma por destinatario al momento de mandar.
            $table->longText('cuerpo')->nullable();
            // users.id es int(11) SIGNED (tabla legacy), no el bigint de Laravel.
            $table->integer('creado_por')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();

            $table->foreign('creado_por')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('envios_masivos_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_masivo_id')->constrained('envios_masivos')->cascadeOnDelete();
            $table->string('email', 255);
            // pendiente | enviado | error | omitido
            $table->string('estado', 20)->default('pendiente');
            $table->text('detalle')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['envio_masivo_id', 'email']);
            $table->index('estado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('envios_masivos_destinatarios');
        Schema::dropIfExists('envios_masivos');
    }
};
