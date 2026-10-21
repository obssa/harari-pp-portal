<?php

namespace App\Domain\Donations\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Donations\Enums\DonationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A pledge to give to the party, and the payment recorded against it
 * (ADR-0060). The audit log keeps amounts, status and receipt numbers,
 * never the donor's details.
 *
 * @property string $id
 * @property string $reference
 * @property string $locale
 * @property string $name
 * @property ?string $phone
 * @property ?string $phone_hash
 * @property ?string $email
 * @property ?string $email_hash
 * @property bool $anonymous
 * @property ?string $purpose
 * @property string $amount
 * @property string $currency
 * @property string $method
 * @property DonationStatus $status
 * @property ?string $received_amount
 * @property ?CarbonImmutable $received_on
 * @property ?string $payment_reference
 * @property ?string $receipt_no
 * @property ?string $cancel_reason
 * @property ?int $recorded_by
 * @property ?CarbonImmutable $erased_at
 * @property CarbonImmutable $created_at
 * @property-read ?User $recorder
 */
class Donation extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    protected $guarded = ['id'];

    protected $hidden = ['name', 'phone', 'email'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'anonymous' => 'boolean',
            'amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'status' => DonationStatus::class,
            'received_on' => 'immutable_date',
            'erased_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('donations')
            ->logOnly(['reference', 'amount', 'currency', 'method', 'status', 'received_amount', 'received_on', 'receipt_no'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** A reference the donor writes on the transfer: DON- and eight letters or digits that cannot be confused. */
    public static function newReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= '23456789ABCDEFGHJKMNPQRSTVWXYZ'[random_int(0, 29)];
            }
            $reference = 'DON-'.substr($code, 0, 4).'-'.substr($code, 4);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** The next receipt number; numbers are never reused. */
    public static function nextReceipt(): string
    {
        $current = DB::table('sequences')->where('name', 'donation_receipt')->lockForUpdate()->value('value');
        if ($current === null) {
            DB::table('sequences')->insert(['name' => 'donation_receipt', 'value' => 0]);
            $current = 0;
        }
        $next = (int) $current + 1;
        DB::table('sequences')->where('name', 'donation_receipt')->update(['value' => $next]);

        return 'RCPT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** The donor's name, or a dash once their details are erased. */
    public function displayName(): string
    {
        return $this->erased_at !== null ? '—' : $this->name;
    }
}
