<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsBrandedEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeePasswordChangedMail extends Mailable implements ShouldQueue
{
    use BuildsBrandedEmail, Queueable, SerializesModels;

    public function __construct(
        public readonly User $employee,
        public readonly string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Smart Inspection password was changed',
        );
    }

    public function content(): Content
    {
        return Content::make(htmlString: $this->renderBrandedEmail(
            'Your password was changed',
            $this->buildBody(),
        ));
    }

    private function buildBody(): string
    {
        $intro = '<p style="margin:0 0 20px;font-size:14px;line-height:22px;color:#4a4a55;">'
            .'An administrator changed the password on your Smart Inspection account. Use the new password below next time you sign in.</p>';

        $rows = $this->renderCredentialRow('Email', $this->employee->email ?? '—')
            .$this->renderCredentialRow('New password', $this->plainPassword);

        $table = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">{$rows}</table>";

        $notice = '<p style="margin:20px 0 0;font-size:13px;line-height:20px;color:#8b8b96;">'
            .'If you did not expect this, contact your supervisor.</p>';

        return $intro.$table.$notice;
    }
}
