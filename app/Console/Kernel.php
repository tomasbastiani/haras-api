<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // Acuses de recibo que el titular dejó vencer.
        $schedule->command('paqueteria:cerrar-acuses')->hourly();

        // Push agrupadas del chat interno. Cada minuto porque un aviso de chat
        // que llega diez minutos tarde ya no sirve; el comando sale enseguida si
        // no hay nada, y el minuto de gracia evita notificar lo que la persona
        // ya leyó en la app.
        $schedule->command('mensajeria:notificar')
            ->everyMinute()
            ->withoutOverlapping();

        // Auditoría de la bitácora sellada. Si alguien tocó la base por fuera
        // de la app, queremos enterarnos solos y no el día que haya un reclamo.
        $schedule->command('paqueteria:verificar-cadena')
            ->dailyAt('03:00')
            ->appendOutputTo(storage_path('logs/paqueteria-cadena.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
