<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Governance (PLAN.md §11 data export and deletion, ADR-0041): tickets get
 * the same keyed hashes of email and phone as registrations, so a person's
 * records can be found without decrypting everything; erased records are
 * marked; every privacy request is kept without the person's details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->char('email_hash', 64)->nullable()->after('phone');
            $table->char('phone_hash', 64)->nullable()->after('email_hash');
            $table->timestamp('erased_at')->nullable()->after('resolved_at');
            $table->index('email_hash');
            $table->index('phone_hash');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->timestamp('erased_at')->nullable()->after('cancelled_at');
            $table->index('email_hash');
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 10);
            $table->char('subject_hash', 64);
            $table->json('found');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        $hash = fn (string $value): string => hash_hmac('sha256', $value, (string) config('app.key'));
        DB::table('form_submissions')->select(['id', 'email', 'phone'])->orderBy('id')->chunk(200, function ($rows) use ($hash): void {
            foreach ($rows as $row) {
                $email = $row->email !== null ? mb_strtolower(trim((string) Crypt::decryptString($row->email))) : null;
                $phone = $row->phone !== null ? (string) Crypt::decryptString($row->phone) : null;
                DB::table('form_submissions')->where('id', $row->id)->update([
                    'email_hash' => $email ? $hash($email) : null,
                    'phone_hash' => $phone ? $hash($phone) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropIndex(['email_hash']);
            $table->dropColumn('erased_at');
        });
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropIndex(['email_hash']);
            $table->dropIndex(['phone_hash']);
            $table->dropColumn(['email_hash', 'phone_hash', 'erased_at']);
        });
    }
};
