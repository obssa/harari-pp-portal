<?php

namespace App\Domain\Forms\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a ticket's history (ADR-0028): an internal note, a reply sent
 * to the person, a change of status or of assignee. The text is encrypted.
 *
 * @property int $id
 * @property string $form_submission_id
 * @property ?int $user_id
 * @property string $kind
 * @property ?string $body
 * @property CarbonImmutable $created_at
 * @property-read FormSubmission $submission
 * @property-read ?User $user
 */
class SubmissionNote extends Model
{
    public const NOTE = 'note';

    public const REPLY = 'reply';

    public const STATUS = 'status';

    public const ASSIGN = 'assign';

    protected $fillable = ['user_id', 'kind', 'body'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
