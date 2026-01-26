<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetLink;

    public function __construct(string $resetLink)
    {
        $this->resetLink = $resetLink;
    }

    public function build()
    {
        return $this->subject('Recuperar contraseña - Haras')
            ->view('emails.reset-password')
            ->with([
                'resetLink' => $this->resetLink,
            ]);
    }
}
