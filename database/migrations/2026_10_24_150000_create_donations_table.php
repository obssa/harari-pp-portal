<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pledges and the payments recorded against them (ADR-0060). The donor's
     * name and contacts are encrypted and looked up by keyed hashes; amounts
     * and receipt numbers stay when a donor's details are erased.
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('locale', 5);
            $table->text('name');
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable()->index();
            $table->boolean('anonymous')->default(false);
            $table->string('purpose', 200)->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('method', 30);
            $table->string('status', 12)->default('pledged')->index();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->date('received_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('receipt_no', 20)->nullable()->unique();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
