<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('booked_on');
            $table->bigInteger('amount');
            $table->string('type', 10);
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('counterparty_name', 150)->nullable();
            $table->string('counterparty_account', 50)->nullable();
            $table->string('variable_symbol', 10)->nullable();
            $table->string('message', 255)->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('source', 10);
            $table->timestamps();

            $table->index('booked_on');
            $table->index(['bank_account_id', 'booked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
