<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmationReservation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function build(): self
    {
        return $this->subject('Confirmation de votre réservation — Hôtelière 2.0')
                    ->view('emails.reservation_confirmee');
    }
}
