<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_product_update_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('update_id', 128);
            $table->timestamp('read_at');
            $table->unique(['user_id', 'update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_product_update_reads');
    }
};
