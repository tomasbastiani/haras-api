<?php

namespace App\Mail;

use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TurnoCanceladoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Turno $turno;

    public function __construct(Turno $turno)
    {
        $this->turno = $turno;
        $this->subject('Turno cancelado - Haras Santa María');
    }

    public function build()
    {
        return $this->view('emails.turno-cancelado')->with([
            'turno' => $this->turno,
        ]);
    }
}
