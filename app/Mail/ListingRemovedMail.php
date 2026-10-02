<?php

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ListingRemovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sellerName,
        public string $listingTitle,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tin đăng Relic của bạn đã bị gỡ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.listing-removed',
        );
    }
}
