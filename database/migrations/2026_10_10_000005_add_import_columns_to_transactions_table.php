<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->date('posted_on')->nullable()->after('booked_on');
            $table->string('bank_reference', 50)->nullable()->after('note');
            $table->string('import_key', 64)->nullable()->unique()->after('bank_reference');
            $table->foreignId('statement_import_id')->nullable()->after('import_key')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('statement_import_id');
            $table->dropUnique(['import_key']);
            $table->dropColumn(['posted_on', 'bank_reference', 'import_key']);
        });
    }
};
