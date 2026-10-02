<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $userName, public string $code, public string $target = 'Mật khẩu')
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã xác thực đổi ' . mb_strtolower($this->target) . ' Relic');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-code');
    }
}
