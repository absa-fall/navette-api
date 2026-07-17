<?php
namespace App\Mail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CodeActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $enseignant;
    public $code;

    public function __construct(User $enseignant, string $code)
    {
        $this->enseignant = $enseignant;
        $this->code = $code;
    }

    public function build()
    {
        return $this
            ->subject('Activez votre compte UADB Mobilité')
            ->view('emails.code-activation');
    }
}