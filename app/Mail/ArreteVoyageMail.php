<?php

namespace App\Mail;

use App\Models\ArreteVoyage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class ArreteVoyageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $arrete;
    public $enseignant;

   public function __construct(ArreteVoyage $arrete, ?User $enseignant = null)
    {
        $this->arrete     = $arrete;
        $this->enseignant = $enseignant;
    }

    public function build()
    {
        $pdf = Pdf::loadView('pdfs.arrete-voyage', [
            'arrete' => $this->arrete,
        ])->setPaper('a4', 'portrait');

        return $this
            ->subject('Arrêté de voyage d\'études N° ' . $this->arrete->numero)
            ->view('emails.arrete-voyage')
            ->attachData(
                $pdf->output(),
                'arrete-' . $this->arrete->numero . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}