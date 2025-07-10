<?php

namespace App\Listeners;

use App\Events\EmailVerificationEvent;
use App\Jobs\EmailVerificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationMail;


class EmailVerificationListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EmailVerificationEvent $event): void
    {
        Mail::send(new VerificationMail($event->email));
    }
}
