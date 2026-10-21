<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imports of existing membership rolls (ADR-0047): the checked rows stay
 * encrypted until they are applied or discarded; members remember the
 * import that made them so it can be rolled back. Member numbers come
 * from a sequence, so a rolled-back import never frees numbers for reuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name', 190);
            $table->string('status', 16);
            $table->json('settings');
            $table->longText('rows')->nullable();
            $table->json('problems');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('ready')->default(0);
            $table->unsignedInteger('with_problems')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'status']);
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('import_id')->nullable()->after('application_id')->constrained('member_imports')->nullOnDelete();
        });

        Schema::create('sequences', function (Blueprint $table): void {
            $table->string('name', 40)->primary();
            $table->unsignedBigInteger('value');
        });
        $last = DB::table('members')->where('member_no', 'like', 'HPP-%')->orderByDesc('member_no')->value('member_no');
        DB::table('sequences')->insert(['name' => 'member_no', 'value' => $last !== null ? (int) substr((string) $last, 4) : 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::table('members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('import_id');
        });
        Schema::dropIfExists('member_imports');
    }
};
