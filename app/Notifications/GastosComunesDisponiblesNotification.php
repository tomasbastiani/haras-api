<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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
        $loginUrl = 'https://harassantamaria.com.ar/login';
        $logoUrl  = 'https://harassantamaria.com.ar/icons/icon-512x512.png';

        $email = $notifiable->routeNotificationFor('mail');

        $nombre = DB::table('gastoscomunes_notificaciones')
            ->where('email', $email)
            ->value('nombre') ?? 'vecino';

        // Obtener lotes
        $lotes = DB::table('gastoscomunes_notificaciones')
            ->where('email', $email)
            ->pluck('nlote')
            ->unique()
            ->toArray();

        $lotesTexto = !empty($lotes)
            ? implode(', ', $lotes)
            : 'sus lotes';

        // Datos de pago (CVU / alias) de los lotes de este vecino. Si todos sus
        // lotes comparten los mismos, se muestran una sola vez; si difieren, se
        // listan por lote, porque un único "CVU:" sería ambiguo y podría llevar
        // a pagar un lote a la cuenta de otro.
        $pagos = DB::table('gastoscomunes_notificaciones')
            ->where('email', $email)
            ->where(function ($q) {
                $q->whereNotNull('cvu')->orWhereNotNull('alias');
            })
            ->orderBy('nlote')
            ->get(['nlote', 'cvu', 'alias'])
            ->unique(fn ($fila) => $fila->nlote . '|' . $fila->cvu . '|' . $fila->alias)
            ->values();

        $pagoUnico = $pagos->unique(fn ($fila) => $fila->cvu . '|' . $fila->alias)->count() === 1
            ? $pagos->first()
            : null;

        $subject = 'Nuevos gastos comunes disponibles';

        $mail = new MailMessage;

        // Envío masivo: va por el stream broadcast de Postmark, que agrega el
        // link de baja (ver config/mail.php, stream_masivo).
        $stream = config('mail.stream_masivo');
        if ($stream) {
            $mail->withSymfonyMessage(function ($message) use ($stream) {
                $message->getHeaders()->addTextHeader('X-PM-Message-Stream', $stream);
            });
        }

        return $mail
            ->subject($subject)
            ->view('emails.gastos-comunes-disponibles', [
                'nombre'      => $nombre,
                'periodo'     => $this->periodo,
                'loginUrl'    => $loginUrl,
                'logoUrl'     => $logoUrl,
                'lotes'       => $lotesTexto,
                'pagoUnico'   => $pagoUnico,
                'pagosPorLote' => $pagoUnico ? collect() : $pagos,
            ]);
    }
}
