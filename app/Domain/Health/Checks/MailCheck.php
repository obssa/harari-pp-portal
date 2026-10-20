<?php

namespace App\Domain\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Whether the portal can send mail at all (ADR-0037): a real mailer and a
 * sender address of the branch. Nothing is sent; "Send a test email" on the
 * health page does that.
 */
final class MailCheck extends Check
{
    private const NOT_SENDING = ['log', 'array', 'null'];

    public function run(): Result
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $host = (string) config("mail.mailers.{$mailer}.host", '');
        $result = Result::make()->meta(['mailer' => $mailer, 'from' => $from])->shortSummary($mailer);

        if (in_array($mailer, self::NOT_SENDING, true)) {
            return app()->isProduction()
                ? $result->failed("Mail is not sent: the mailer is \"{$mailer}\".")
                : $result->warning("Mail is not sent: the mailer is \"{$mailer}\".");
        }
        if ($mailer === 'smtp' && $host === '') {
            return $result->failed('The SMTP host is not set.');
        }
        if ($from === '' || str_ends_with($from, '@example.com')) {
            return $result->warning('The sender address is not set.');
        }

        return $result->ok();
    }
}
