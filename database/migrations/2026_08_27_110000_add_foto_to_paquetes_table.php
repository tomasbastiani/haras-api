<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto del paquete al ingresar.
     *
     * Se guarda la ruta, no la imagen: el archivo va al disco privado igual que
     * la firma del acta, porque es material del expediente y no tiene por qué
     * quedar accesible por URL pública.
     */
    public function up()
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('observaciones');
        });
    }

    public function down()
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->dropColumn('foto_path');
        });
    }
};
