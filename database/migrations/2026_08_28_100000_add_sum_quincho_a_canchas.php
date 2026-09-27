<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El turnero deja fútbol/tenis (por ahora) y pasa a SUM y Quincho: dos
     * utilidades únicas, sin réplicas como tenían las canchas (4 de fútbol,
     * 16 de tenis). Los tipos viejos no se borran ni sus canchas: quedan
     * inactivas para no romper turnos históricos que ya las referencian.
     */
    public function up()
    {
        DB::statement("ALTER TABLE canchas MODIFY tipo ENUM('futbol','tenis','sum','quincho') NOT NULL");

        DB::table('canchas')->whereIn('tipo', ['futbol', 'tenis'])->update(['activa' => false]);

        DB::table('canchas')->updateOrInsert(
            ['nombre' => 'SUM'],
            ['tipo' => 'sum', 'activa' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('canchas')->updateOrInsert(
            ['nombre' => 'Quincho'],
            ['tipo' => 'quincho', 'activa' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down()
    {
        DB::table('canchas')->whereIn('tipo', ['sum', 'quincho'])->delete();
        DB::table('canchas')->whereIn('tipo', ['futbol', 'tenis'])->update(['activa' => true]);
        DB::statement("ALTER TABLE canchas MODIFY tipo ENUM('futbol','tenis') NOT NULL");
    }
};
