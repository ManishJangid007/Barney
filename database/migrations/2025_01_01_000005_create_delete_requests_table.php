<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delete_requests', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, done
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delete_requests');
    }
};
