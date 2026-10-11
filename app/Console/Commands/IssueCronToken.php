<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CronToken;
use Illuminate\Console\Command;

class IssueCronToken extends Command
{
    protected $signature = 'cron:token
        {--days=30 : How many days the token stays valid}';

    protected $description = 'Issue an expiring token for GET /cron/run, signed with CRON_SECRET';

    public function handle(): int
    {
        $secret = (string) config('trypost.cron.secret');
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($secret === '') {
            $this->error('CRON_SECRET is empty, so /cron/run is disabled. Set it first.');

            return self::FAILURE;
        }

        if ($days === false || $days < 1) {
            $this->error('--days must be a whole number of at least 1.');

            return self::FAILURE;
        }

        $expiresAt = now()->addDays($days);

        $this->line(CronToken::issue($secret, $expiresAt));
        $this->info("Valid until {$expiresAt->toDateTimeString()} UTC. Send it as `Authorization: Bearer <token>` or `?token=<token>`.");

        return self::SUCCESS;
    }
}
