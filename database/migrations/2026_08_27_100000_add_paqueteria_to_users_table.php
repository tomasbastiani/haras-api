<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta dedicada de paquetería (portería).
     *
     * Va como columna en `users` y no como tabla aparte —a diferencia de
     * `paqueteria_operarios`, que es un permiso aditivo sobre la cuenta de un
     * vecino— porque acá el flag define QUÉ ES la cuenta, no qué puede hacer de
     * más: el personal de portería no tiene lote, no ve gastos comunes y entra
     * directo a la oficina. Es el mismo rango que `admin`, que ya vive acá.
     *
     * `paqueteria_operarios` sigue vigente y se sigue chequeando: quien ya
     * estaba dado de alta ahí no pierde el acceso.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('paqueteria')->default(false)->after('admin');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('paqueteria');
        });
    }
};
