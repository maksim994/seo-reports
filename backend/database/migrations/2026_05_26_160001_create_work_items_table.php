<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('category', 32);
            $table->text('description');
            $table->timestamps();

            $table->index(['project_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
