<?php

namespace App\Mail;

use App\Models\AutorisationAbsence;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class AutorisationAbsenceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $autorisation;

    public function __construct(AutorisationAbsence $autorisation)
    {
        $this->autorisation = $autorisation;
    }

    public function build()
    {
        $pdf = Pdf::loadView('pdfs.autorisation-absence', [
            'autorisation' => $this->autorisation,
        ])->setPaper('a4', 'portrait');

        return $this
            ->subject('Autorisation d\'absence N° ' . $this->autorisation->numero)
            ->view('emails.autorisation-absence')
            ->attachData(
                $pdf->output(),
                'autorisation-' . $this->autorisation->numero . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}