<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('bank', 20);
            $table->date('period_from');
            $table->date('period_to');
            $table->bigInteger('opening_balance');
            $table->bigInteger('closing_balance');
            $table->string('file_hash', 64)->index();
            $table->unsignedInteger('imported_count');
            $table->unsignedInteger('skipped_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_imports');
    }
};
