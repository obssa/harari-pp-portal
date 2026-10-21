<?php

namespace App\Domain\Membership\Jobs;

use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Models\MemberImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Adds the checked rows of a membership roll (ADR-0047). Runs in the queue:
 * a roll of thousands of members takes longer than a page request may.
 */
final class ApplyMemberImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function handle(ImportMembers $imports): void
    {
        $import = MemberImport::query()->withoutGlobalScopes()->find($this->importId);
        if ($import instanceof MemberImport) {
            $imports->run($import);
        }
    }

    public function failed(?Throwable $e): void
    {
        $import = MemberImport::query()->withoutGlobalScopes()->find($this->importId);
        if ($import instanceof MemberImport) {
            app(ImportMembers::class)->failed($import);
        }
    }
}
