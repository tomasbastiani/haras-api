<?php

namespace App\Mail;

use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TurnoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Turno $turno;

    public function __construct(Turno $turno)
    {
        $this->turno = $turno;
        $this->subject('Turno confirmado - Haras Santa María');
    }

    public function build()
    {
        return $this->view('emails.turno-confirmado')->with([
            'turno' => $this->turno,
        ]);
    }
}
