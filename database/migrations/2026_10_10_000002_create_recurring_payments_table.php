<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->bigInteger('amount');
            $table->string('frequency', 20);
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('counterparty_account', 50)->nullable();
            $table->string('match_text', 100)->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_payments');
    }
};
