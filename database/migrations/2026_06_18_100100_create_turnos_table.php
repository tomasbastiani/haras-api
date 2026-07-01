<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancha_id')->constrained('canchas');
            // users.id es int(11) signed (tabla legacy) — no usamos foreignId() porque
            // genera bigint unsigned, incompatible para la FK.
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('nlote')->nullable();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->enum('estado', ['reservado', 'cancelado'])->default('reservado');
            $table->timestamp('cancelado_at')->nullable();
            $table->integer('cancelado_por')->nullable();
            $table->foreign('cancelado_por')->references('id')->on('users');
            $table->timestamps();

            $table->index(['cancha_id', 'fecha']);
            $table->index(['user_id', 'fecha']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('turnos');
    }
};
