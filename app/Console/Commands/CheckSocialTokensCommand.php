<?php

namespace App\Console\Commands;

use App\Domain\Social\Actions\CheckFacebookTokens;
use Illuminate\Console\Command;

/**
 * Asks Meta every morning when the Facebook page tokens run out and warns
 * social media managers in time (ADR-0038).
 */
final class CheckSocialTokensCommand extends Command
{
    protected $signature = 'social:check-tokens';

    protected $description = 'Check when the Facebook page tokens expire and warn before they do';

    public function handle(CheckFacebookTokens $check): int
    {
        $result = $check->handle();
        $this->line("{$result['checked']} token(s) checked, {$result['warned']} warning(s) sent.");

        return self::SUCCESS;
    }
}
