<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // bank, cash, wallet
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
