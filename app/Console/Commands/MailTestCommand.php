<?php

namespace App\Console\Commands;

use App\Services\Mail\MailTester;
use Illuminate\Console\Command;

class MailTestCommand extends Command
{
    protected $signature = 'lg:mail-test {email : Where to send the test email}';

    protected $description = 'Send a test email right away to check the SMTP settings in .env';

    public function handle(MailTester $tester): int
    {
        $result = $tester->send($this->argument('email'));
        $result['ok'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
