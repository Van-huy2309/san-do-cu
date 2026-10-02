<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $reason,
        public bool $locked = true,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->locked ? 'Tài khoản Relic đã bị khóa' : 'Tài khoản Relic đã được mở khóa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-locked',
        );
    }
}
