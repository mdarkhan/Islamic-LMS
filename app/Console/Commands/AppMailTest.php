<?php

namespace App\Console\Commands;

use App\Mail\AskUstazQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AppMailTest extends Command
{
    protected $signature = 'app:mail-test {--to= : Recipient; defaults to USTAZ_EMAIL}';

    protected $description = 'Send a safe Bengali SMTP/Ask Ustaz diagnostic message';

    public function handle(): int
    {
        $to = (string) ($this->option('to') ?: config('mail.ustaz_email'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --to address or USTAZ_EMAIL is required.');

            return self::INVALID;
        }

        try {
            Mail::to($to)->send(new AskUstazQuestion(
                name: 'SMTP Diagnostic',
                email: (string) config('mail.from.address'),
                mobile: null,
                topic: 'ইমেইল পরীক্ষা',
                question: 'এটি মাসউদ আলিমী production-readiness SMTP পরীক্ষা। কোনো উত্তর প্রয়োজন নেই।',
                submittedAt: now()->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s T'),
            ));
        } catch (\Throwable $e) {
            $this->error('Mail delivery failed ('.$e::class.'). No credentials were printed.');

            return self::FAILURE;
        }

        $this->info('Diagnostic message accepted by the configured mail transport.');

        return self::SUCCESS;
    }
}
