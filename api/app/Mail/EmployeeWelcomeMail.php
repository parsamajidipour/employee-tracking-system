<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsBrandedEmail;
use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeWelcomeMail extends Mailable implements ShouldQueue
{
    use BuildsBrandedEmail, Queueable, SerializesModels;

    public function __construct(
        public readonly User $employee,
        public readonly string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Smart Inspection — your account is ready',
        );
    }

    public function content(): Content
    {
        return Content::make(htmlString: $this->renderBrandedEmail(
            "Welcome, {$this->employee->name}",
            $this->buildBody(),
        ));
    }

    private function buildBody(): string
    {
        $intro = '<p style="margin:0 0 20px;font-size:14px;line-height:22px;color:#4a4a55;">'
            .'An account was created for you on Smart Inspection. Use either of the details below to sign in on the mobile app.</p>';

        $rows = $this->renderCredentialRow('Email', $this->employee->email ?? '—')
            .($this->employee->phone ? $this->renderCredentialRow('Phone', $this->employee->phone) : '')
            .$this->renderCredentialRow('Password', $this->plainPassword);

        $table = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">{$rows}</table>";

        $latestRelease = AppRelease::query()->orderByDesc('version_code')->first();
        $button = $latestRelease !== null
            ? $this->renderButton(route('app-releases.download', $latestRelease->id), 'Download the app')
            : '';

        return $intro.$table.$button;
    }
}
