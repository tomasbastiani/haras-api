<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de ruteo: define a quién le llega cada categoría de reclamo.
     *
     * Vive en base y no en el prompt del bot a propósito: cambiar el mail del
     * proveedor de jardinería no debe requerir tocar código ni redeployar, y el
     * modelo nunca inventa un destinatario (sólo elige una categoría del enum).
     */
    public function up()
    {
        Schema::create('reclamo_ruteos', function (Blueprint $table) {
            $table->id();
            $table->string('categoria')->unique();
            $table->string('nombre');
            $table->string('email')->nullable();
            // users.id es int(11) signed (tabla legacy) — mismo criterio que turnos.
            $table->integer('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $ahora = now();

        DB::table('reclamo_ruteos')->insert([
            ['categoria' => 'mantenimiento',   'nombre' => 'Mantenimiento',        'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'seguridad',       'nombre' => 'Seguridad',            'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'alumbrado',       'nombre' => 'Alumbrado público',    'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'agua_cloacas',    'nombre' => 'Agua y cloacas',       'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'espacios_verdes', 'nombre' => 'Espacios verdes',      'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'obras',           'nombre' => 'Comité de obras',      'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'convivencia',     'nombre' => 'Convivencia',          'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['categoria' => 'administracion',  'nombre' => 'Administración',       'email' => null, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('reclamo_ruteos');
    }
};
