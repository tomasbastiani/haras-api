<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CVU y alias de pago de cada lote, para mostrarlos en el aviso por mail de
 * gastos comunes. Los carga el admin en el Excel de /import-gastos.
 *
 * La tabla no tiene migración de creación en este repo (se creó a mano en
 * producción), así que acá sólo se agregan las columnas. Ambas NULL: las filas
 * ya importadas quedan sin CVU/alias hasta el próximo import, y el mail
 * simplemente no los muestra.
 *
 * Tamaños: el CVU/CBU es siempre de 22 dígitos, y el alias del BCRA va de 6 a
 * 20 caracteres. Se guardan como texto, nunca como número: 22 dígitos no entran
 * en un entero y como float se pierden los últimos.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('gastoscomunes_notificaciones', function (Blueprint $table) {
            $table->string('cvu', 22)->nullable()->after('nlote');
            $table->string('alias', 20)->nullable()->after('cvu');
        });
    }

    public function down()
    {
        Schema::table('gastoscomunes_notificaciones', function (Blueprint $table) {
            $table->dropColumn(['cvu', 'alias']);
        });
    }
};
