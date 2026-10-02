<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $bodyContent;
    public string $subjectText;

    public function __construct(string $subjectText, string $bodyContent)
    {
        $this->subjectText = $subjectText;
        $this->bodyContent = $bodyContent;

        $this->subject($subjectText);

        // Envío masivo: va por el stream broadcast de Postmark, que agrega el
        // link de baja (ver config/mail.php, stream_masivo).
        $stream = config('mail.stream_masivo');
        if ($stream) {
            $this->withSymfonyMessage(function ($message) use ($stream) {
                $message->getHeaders()->addTextHeader('X-PM-Message-Stream', $stream);
            });
        }
    }

    public function build()
    {
        return $this
            ->view('emails.custom-admin')
            ->with([
                'bodyContent' => $this->bodyContent, // sin nl2br acá
                'subject'     => $this->subjectText,
            ]);
    }
}
