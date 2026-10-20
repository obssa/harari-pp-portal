<?php

namespace App\Console\Commands;

use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Models\FormSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes messages and tickets resolved longer ago than the retention period,
 * with their attachments and notes (ADR-0041). Open tickets are never
 * deleted.
 */
final class PurgeSubmissionsCommand extends Command
{
    protected $signature = 'forms:purge-submissions';

    protected $description = 'Delete resolved tickets past the retention period';

    public function handle(): int
    {
        $before = CarbonImmutable::now()->subDays(max(30, (int) config('portal.submission_retention_days')));
        $deleted = 0;

        FormSubmission::query()
            ->where('status', SubmissionStatus::Resolved->value)
            ->where('resolved_at', '<', $before)
            ->chunkById(100, function ($submissions) use (&$deleted): void {
                foreach ($submissions as $submission) {
                    /** @var FormSubmission $submission */
                    Storage::disk(SubmitForm::FILE_DISK)->deleteDirectory("form-submissions/{$submission->id}");
                    $submission->notes()->delete();
                    $submission->delete();
                    $deleted++;
                }
            });

        $this->components->info("Tickets deleted: {$deleted}");

        return self::SUCCESS;
    }
}
