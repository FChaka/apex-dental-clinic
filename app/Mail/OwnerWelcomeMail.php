<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ownerName,
        public string $username,
        public string $temporaryPin,
        public string $loginUrl,
        public string $pinExpiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your clinic is ready — log in now',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-welcome',
        );
    }
}
