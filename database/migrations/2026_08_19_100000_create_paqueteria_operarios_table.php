<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién puede operar la paquetería. Mismo criterio que reclamo_ruteos: el
     * de portería tiene que poder recibir y entregar paquetes sin ser admin
     * de todo el sistema (gastos, usuarios, mails masivos).
     */
    public function up()
    {
        Schema::create('paqueteria_operarios', function (Blueprint $table) {
            $table->id();
            // users.id es int(11) signed (tabla legacy) — mismo criterio que reclamos.
            $table->integer('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paqueteria_operarios');
    }
};
