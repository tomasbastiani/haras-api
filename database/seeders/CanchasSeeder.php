<?php

namespace Database\Seeders;

use App\Models\Cancha;
use Illuminate\Database\Seeder;

class CanchasSeeder extends Seeder
{
    public function run()
    {
        $canchas = [
            ['nombre' => 'Cancha de Fútbol 1',  'tipo' => 'futbol', 'orden' => 1],
            ['nombre' => 'Cancha de Fútbol 2',  'tipo' => 'futbol', 'orden' => 2],
            ['nombre' => 'Cancha de Fútbol 3',  'tipo' => 'futbol', 'orden' => 3],
            ['nombre' => 'Cancha de Fútbol 4',  'tipo' => 'futbol', 'orden' => 4],
            ['nombre' => 'Cancha de Tenis 1',   'tipo' => 'tenis',  'orden' => 1],
            ['nombre' => 'Cancha de Tenis 2',   'tipo' => 'tenis',  'orden' => 2],
            ['nombre' => 'Cancha de Tenis 3',   'tipo' => 'tenis',  'orden' => 3],
            ['nombre' => 'Cancha de Tenis 4',   'tipo' => 'tenis',  'orden' => 4],
            ['nombre' => 'Cancha de Tenis 5',   'tipo' => 'tenis',  'orden' => 5],
            ['nombre' => 'Cancha de Tenis 6',   'tipo' => 'tenis',  'orden' => 6],
            ['nombre' => 'Cancha de Tenis 7',   'tipo' => 'tenis',  'orden' => 7],
            ['nombre' => 'Cancha de Tenis 8',   'tipo' => 'tenis',  'orden' => 8],
            ['nombre' => 'Cancha de Tenis 9',   'tipo' => 'tenis',  'orden' => 9],
            ['nombre' => 'Cancha de Tenis 10',  'tipo' => 'tenis',  'orden' => 10],
            ['nombre' => 'Cancha de Tenis 11',  'tipo' => 'tenis',  'orden' => 11],
            ['nombre' => 'Cancha de Tenis 12',  'tipo' => 'tenis',  'orden' => 12],
            ['nombre' => 'Cancha de Tenis 13',  'tipo' => 'tenis',  'orden' => 13],
            ['nombre' => 'Cancha de Tenis 14',  'tipo' => 'tenis',  'orden' => 14],
            ['nombre' => 'Cancha de Tenis 15',  'tipo' => 'tenis',  'orden' => 15],
            ['nombre' => 'Cancha de Tenis 16',  'tipo' => 'tenis',  'orden' => 16],
        ];

        foreach ($canchas as $cancha) {
            Cancha::firstOrCreate(
                ['nombre' => $cancha['nombre']],
                $cancha
            );
        }
    }
}
