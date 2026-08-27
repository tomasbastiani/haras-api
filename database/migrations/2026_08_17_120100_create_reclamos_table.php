<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reclamos', function (Blueprint $table) {
            $table->id();
            // users.id es int(11) signed (tabla legacy) — no usamos foreignId()
            // porque genera bigint unsigned, incompatible para la FK.
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('nlote')->nullable();

            $table->enum('categoria', [
                'mantenimiento',
                'seguridad',
                'alumbrado',
                'agua_cloacas',
                'espacios_verdes',
                'obras',
                'convivencia',
                'administracion',
            ]);
            $table->enum('urgencia', ['emergencia', 'alta', 'normal'])->default('normal');
            $table->enum('ubicacion_tipo', ['lote', 'espacio_comun']);
            $table->string('ubicacion_detalle');

            $table->string('resumen');
            $table->text('descripcion');

            $table->enum('estado', ['nuevo', 'tomado', 'resuelto', 'cerrado'])->default('nuevo');
            $table->string('derivado_a')->nullable();       // nombre del área, congelado al crear
            $table->string('derivado_email')->nullable();   // destinatario efectivo al crear
            $table->integer('tomado_por')->nullable();
            $table->foreign('tomado_por')->references('id')->on('users');
            $table->timestamp('tomado_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->text('nota_cierre')->nullable();

            $table->timestamps();

            $table->index(['estado', 'categoria']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reclamos');
    }
};
