<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OwnerWelcomeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOwnerWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $contactEmail,
        public string $ownerName,
        public string $username,
        public string $temporaryPin,
        public string $clinicSlug,
        public string $pinExpiresAt,
    ) {}

    public function handle(): void
    {
        $domain = (string) config('app.platform_domain');

        Mail::to($this->contactEmail)->send(new OwnerWelcomeMail(
            ownerName: $this->ownerName,
            username: $this->username,
            temporaryPin: $this->temporaryPin,
            loginUrl: 'https://'.$this->clinicSlug.'.'.$domain.'/login',
            pinExpiresAt: $this->pinExpiresAt,
        ));
    }
}
