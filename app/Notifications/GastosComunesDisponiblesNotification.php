<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class GastosComunesDisponiblesNotification extends Notification
{
    use Queueable;

    protected ?string $periodo;

    public function __construct(?string $periodo = null)
    {
        $this->periodo = $periodo;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // URL al login
        $loginUrl = 'https://harassantamaria.com.ar/login';

        // Nombre (columna `nombre` de tu modelo User)
        $nombre = $notifiable->nombre ?? 'vecino';

        // Logo público (asegurate de tener public/images/hsm.png)
        $logoUrl = 'https://harassantamaria.com.ar/img/hsm.png';

        // Obtener lotes desde la tabla `gastoscomunes` por email
        $lotes = [];

        if (!empty($notifiable->email)) {
            $lotes = DB::table('gastoscomunes')
                ->where('email', $notifiable->email)
                ->pluck('nlote')
                ->unique()
                ->toArray();
        }else{
            $lotes = DB::table('gastoscomunes')
            ->where('email', '1974consultoria@gmail.com') //email de prueba
            ->pluck('nlote')
            ->unique()
            ->toArray();
        }

        $lotesTexto = !empty($lotes)
            ? implode(', ', $lotes)
            : 'sus lotes';

        $subject = 'Nuevos gastos comunes disponibles';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.gastos-comunes-disponibles', [
                'nombre'   => $nombre,
                'periodo'  => $this->periodo,
                'loginUrl' => $loginUrl,
                'logoUrl'  => $logoUrl,
                'lotes'    => $lotesTexto,
            ]);
    }
}
