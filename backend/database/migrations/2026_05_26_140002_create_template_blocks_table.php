<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained()->cascadeOnDelete();
            $table->string('block_type', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['report_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
    }
};
