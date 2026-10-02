<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de "este email rebota": Postmark lo suprimió por hard bounce.
 *
 * La pone y la saca el comando `mail:sincronizar-rebotes` (scheduler, diario),
 * que lee la lista de suprimidas de Postmark. Los envíos masivos (aviso de
 * gastos comunes, mail personalizado) excluyen las direcciones marcadas: cada
 * envío a una dirección muerta suma a la tasa de rebote, y con 14% Postmark
 * frenó la cuenta en agosto de 2026.
 *
 * Es por dirección, no por persona: si el vecino pasa a usar otro email, el
 * nuevo no está marcado. Si se edita el email de un usuario, User lo limpia.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_rebotado_at')->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_rebotado_at');
        });
    }
};
